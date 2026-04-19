/**
 * Pet Match — Socket.IO server: JWT auth, conversation rooms, REST-backed send + realtime sync.
 *
 * Env:
 *   LARAVEL_API_URL       Laravel HTTP origin (no path)
 *   SOCKET_PORT           Listen port (default 6001)
 *   SOCKET_CORS_ORIGIN    Optional; default *
 *   SOCKET_INTERNAL_SECRET  Required for /internal/* (presence + broadcast from Laravel)
 */
import http from "node:http";
import { Server } from "socket.io";

const LARAVEL_API_URL = (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000").replace(
  /\/$/,
  "",
);
const PORT = Number.parseInt(process.env.SOCKET_PORT ?? "6001", 10);
const CORS_ORIGIN = process.env.SOCKET_CORS_ORIGIN ?? "*";
const INTERNAL_SECRET = process.env.SOCKET_INTERNAL_SECRET ?? "";

/** @type {Map<string, number>} */
const onlineConnectionCount = new Map();

function adjustOnlineCount(userId, delta) {
  const prev = onlineConnectionCount.get(userId) ?? 0;
  const next = Math.max(0, prev + delta);
  if (next === 0) {
    onlineConnectionCount.delete(userId);
  } else {
    onlineConnectionCount.set(userId, next);
  }
  return { prev, next };
}

const httpServer = http.createServer(async (req, res) => {
  if (!req.url) {
    return;
  }
  let pathname = req.url;
  try {
    pathname = new URL(req.url, "http://127.0.0.1").pathname;
  } catch {
    return;
  }
  if (!pathname.startsWith("/internal/")) {
    return;
  }
  if (!INTERNAL_SECRET || req.headers["x-internal-secret"] !== INTERNAL_SECRET) {
    res.writeHead(503, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: false, error: "internal routes disabled or bad secret" }));
    return;
  }

  if (req.method === "GET" && pathname.startsWith("/internal/presence/")) {
    const userId = decodeURIComponent(pathname.slice("/internal/presence/".length));
    const online = (onlineConnectionCount.get(userId) ?? 0) > 0;
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ online: Boolean(userId) && online }));
    return;
  }

  if (req.method === "POST" && pathname === "/internal/broadcast") {
    let raw = "";
    for await (const chunk of req) {
      raw += chunk;
    }
    let body;
    try {
      body = raw ? JSON.parse(raw) : {};
    } catch {
      res.writeHead(400, { "Content-Type": "application/json" });
      res.end(JSON.stringify({ ok: false }));
      return;
    }
    const event = typeof body?.event === "string" ? body.event : "";
    const payload = body?.payload && typeof body.payload === "object" ? body.payload : {};
    if (event === "conversation_deleted") {
      const userId = typeof payload.userId === "string" ? payload.userId : "";
      if (userId) {
        io.to(userId).emit("conversation_deleted", payload);
      }
    } else if (event === "messages_read") {
      const cid =
        typeof payload.conversation_id === "string" ? payload.conversation_id.trim() : "";
      if (cid) {
        io.to(`conv:${cid}`).emit("messages_read", payload);
      }
    }
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  res.writeHead(404, { "Content-Type": "application/json" });
  res.end(JSON.stringify({ ok: false }));
});

const io = new Server(httpServer, {
  cors: {
    origin: CORS_ORIGIN === "*" ? true : CORS_ORIGIN.split(",").map((s) => s.trim()),
    methods: ["GET", "POST"],
  },
  transports: ["websocket", "polling"],
});

function extractBearerToken(handshake) {
  const fromAuth = handshake.auth?.token ?? handshake.auth?.access_token;
  if (typeof fromAuth === "string" && fromAuth.trim()) {
    return fromAuth.trim();
  }
  const header = handshake.headers?.authorization;
  if (typeof header === "string" && header.toLowerCase().startsWith("bearer ")) {
    return header.slice(7).trim();
  }
  return null;
}

io.use(async (socket, next) => {
  const token = extractBearerToken(socket.handshake);
  if (!token) {
    next(new Error("Unauthorized: missing token"));
    return;
  }

  try {
    const res = await fetch(`${LARAVEL_API_URL}/api/me`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });

    if (!res.ok) {
      next(new Error("Unauthorized: invalid or expired token"));
      return;
    }

    const body = await res.json();
    const rawId = body?.user?.id;
    const userId =
      typeof rawId === "string"
        ? rawId.trim()
        : typeof rawId === "number" && Number.isFinite(rawId)
          ? String(rawId)
          : "";
    if (!userId) {
      next(new Error("Unauthorized: no user"));
      return;
    }

    socket.data.userId = userId;
    socket.data.bearerToken = token;
    next();
  } catch {
    next(new Error("Unauthorized: verification failed"));
  }
});

function leaveAllConvRooms(socket) {
  for (const room of socket.rooms) {
    if (typeof room === "string" && room.startsWith("conv:")) {
      socket.leave(room);
    }
  }
}

async function listConversationIds(bearerToken) {
  let res;
  try {
    res = await fetch(`${LARAVEL_API_URL}/api/conversations`, {
      headers: {
        Authorization: `Bearer ${bearerToken}`,
        Accept: "application/json",
      },
    });
  } catch {
    return [];
  }
  if (!res.ok) {
    return [];
  }
  const body = await res.json().catch(() => ({}));
  const data = body?.data;
  if (!Array.isArray(data)) {
    return [];
  }
  return data
    .map((c) => c?.id)
    .filter((id) => typeof id === "string" && id.length > 0);
}

async function refreshConversationRooms(socket) {
  const token = socket.data.bearerToken;
  if (typeof token !== "string" || !token) {
    return;
  }
  const ids = await listConversationIds(token);
  leaveAllConvRooms(socket);
  for (const id of ids) {
    socket.join(`conv:${id}`);
  }
}

async function verifyConversationAccess(bearerToken, conversationId) {
  try {
    const res = await fetch(
      `${LARAVEL_API_URL}/api/conversations/${encodeURIComponent(conversationId)}/messages?limit=1`,
      {
        headers: {
          Authorization: `Bearer ${bearerToken}`,
          Accept: "application/json",
        },
      },
    );
    return res.ok;
  } catch {
    return false;
  }
}

function buildOnlineUserIdsSnapshot() {
  const ids = [];
  for (const [uid, count] of onlineConnectionCount) {
    if (count > 0 && typeof uid === "string" && uid.length > 0) {
      ids.push(uid);
    }
  }
  return ids;
}

function emitOnlinePresenceSnapshot(socket) {
  socket.emit("online_presence_snapshot", { userIds: buildOnlineUserIdsSnapshot() });
}

io.on("connection", (socket) => {
  const userId = socket.data.userId;
  socket.join(userId);

  const { prev, next } = adjustOnlineCount(userId, 1);
  if (prev === 0) {
    io.emit("user_online", { userId });
  }

  // eslint-disable-next-line no-console -- operational log
  console.info(`[socket] connected user=${userId} id=${socket.id} onlineCount=${next}`);

  void refreshConversationRooms(socket).catch(() => {});

  socket.on("request_online_snapshot", () => {
    emitOnlinePresenceSnapshot(socket);
  });

  socket.on("sync_conversation_rooms", (_payload, callback) => {
    void (async () => {
      try {
        await refreshConversationRooms(socket);
        if (typeof callback === "function") {
          callback({ success: true });
        }
      } catch (e) {
        if (typeof callback === "function") {
          callback({ success: false, error: String(e?.message ?? e) });
        }
      }
    })();
  });

  socket.on("join_conversation", (payload, callback) => {
    void (async () => {
      const conversationId =
        typeof payload?.conversationId === "string" ? payload.conversationId.trim() : "";
      const token = socket.data.bearerToken;
      if (!conversationId || typeof token !== "string" || !token) {
        if (typeof callback === "function") {
          callback({ success: false, error: "Invalid conversation" });
        }
        return;
      }
      const room = `conv:${conversationId}`;
      if (socket.rooms.has(room)) {
        if (typeof callback === "function") {
          callback({ success: true });
        }
        return;
      }
      const ok = await verifyConversationAccess(token, conversationId);
      if (!ok) {
        if (typeof callback === "function") {
          callback({ success: false, error: "Forbidden or not found" });
        }
        return;
      }
      socket.join(room);
      if (typeof callback === "function") {
        callback({ success: true });
      }
    })().catch((e) => {
      // eslint-disable-next-line no-console -- operational log
      console.error("[socket] join_conversation failed", e);
      if (typeof callback === "function") {
        callback({ success: false, error: String(e?.message ?? e) });
      }
    });
  });

  socket.on("leave_conversation", (payload) => {
    const conversationId =
      typeof payload?.conversationId === "string" ? payload.conversationId.trim() : "";
    if (conversationId) {
      socket.leave(`conv:${conversationId}`);
    }
  });

  socket.on("typing", (payload) => {
    const conversationId =
      typeof payload?.conversationId === "string" ? payload.conversationId.trim() : "";
    const receiverId = typeof payload?.receiverId === "string" ? payload.receiverId.trim() : "";
    const senderId = socket.data.userId;
    if (!conversationId || !receiverId || receiverId === senderId) {
      return;
    }
    const isTyping = Boolean(payload?.isTyping);
    socket.to(receiverId).emit("typing_status", {
      conversationId,
      senderId,
      receiverId,
      isTyping,
    });
  });

  socket.on("send_message", (payload, callback) => {
    void (async () => {
      const conversationId =
        typeof payload?.conversationId === "string" ? payload.conversationId.trim() : "";
      const message = typeof payload?.message === "string" ? payload.message.trim() : "";
      const token = socket.data.bearerToken;

      const fail = (error) => {
        if (typeof callback === "function") {
          callback({ success: false, error });
        }
      };

      if (!conversationId || !message || typeof token !== "string" || !token) {
        fail("Invalid payload");
        return;
      }

      try {
        const res = await fetch(
          `${LARAVEL_API_URL}/api/conversations/${encodeURIComponent(conversationId)}/messages`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              Accept: "application/json",
              "Content-Type": "application/json",
            },
            body: JSON.stringify({ message }),
          },
        );

        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
          fail(typeof body?.message === "string" ? body.message : `HTTP ${res.status}`);
          return;
        }

        const data = body?.data;
        if (!data || typeof data !== "object") {
          fail("Invalid response from API");
          return;
        }

        io.to(`conv:${conversationId}`).emit("new_message", data);

        if (typeof callback === "function") {
          callback({ success: true, data });
        }
      } catch (e) {
        fail(String(e?.message ?? e));
      }
    })();
  });

  socket.on("disconnect", (reason) => {
    const { prev, next } = adjustOnlineCount(userId, -1);
    if (prev > 0 && next === 0) {
      io.emit("user_offline", { userId });
    }
    // eslint-disable-next-line no-console
    console.info(`[socket] disconnect user=${userId} reason=${reason} onlineCount=${next}`);
  });
});

httpServer.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.info(
    `[socket] listening on :${PORT} (Laravel verify: ${LARAVEL_API_URL}/api/me) internal=${INTERNAL_SECRET ? "on" : "off"}`,
  );
});
