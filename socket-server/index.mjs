/**
 * Pet Match — Socket.IO server: JWT auth, conversation rooms, REST-backed send + `new_message` broadcast.
 *
 * Env:
 *   LARAVEL_API_URL  Laravel HTTP origin (no path), e.g. http://127.0.0.1:8000 or http://app:80 in Docker
 *   SOCKET_PORT      Listen port (default 6001)
 *   SOCKET_CORS_ORIGIN  Optional; default * (tighten in production)
 */
import http from "node:http";
import { Server } from "socket.io";

const LARAVEL_API_URL = (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000").replace(
  /\/$/,
  "",
);
const PORT = Number.parseInt(process.env.SOCKET_PORT ?? "6001", 10);
const CORS_ORIGIN = process.env.SOCKET_CORS_ORIGIN ?? "*";

const httpServer = http.createServer();

const io = new Server(httpServer, {
  cors: {
    origin: CORS_ORIGIN === "*" ? true : CORS_ORIGIN.split(",").map((s) => s.trim()),
    methods: ["GET", "POST"],
  },
  transports: ["websocket", "polling"],
});

/**
 * Resolve Bearer token from Socket.IO handshake (matches socket.io-client `auth.token`).
 */
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
    const userId = body?.user?.id;
    if (typeof userId !== "string" || userId.length === 0) {
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
  const res = await fetch(`${LARAVEL_API_URL}/api/conversations`, {
    headers: {
      Authorization: `Bearer ${bearerToken}`,
      Accept: "application/json",
    },
  });
  if (!res.ok) {
    return [];
  }
  const body = await res.json();
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
}

io.on("connection", (socket) => {
  const userId = socket.data.userId;
  socket.join(userId);
  // eslint-disable-next-line no-console -- operational log
  console.info(`[socket] connected user=${userId} id=${socket.id}`);

  void refreshConversationRooms(socket).catch(() => {});

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
      const ok = await verifyConversationAccess(token, conversationId);
      if (!ok) {
        if (typeof callback === "function") {
          callback({ success: false, error: "Forbidden or not found" });
        }
        return;
      }
      socket.join(`conv:${conversationId}`);
      if (typeof callback === "function") {
        callback({ success: true });
      }
    })();
  });

  socket.on("leave_conversation", (payload) => {
    const conversationId =
      typeof payload?.conversationId === "string" ? payload.conversationId.trim() : "";
    if (conversationId) {
      socket.leave(`conv:${conversationId}`);
    }
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
    // eslint-disable-next-line no-console
    console.info(`[socket] disconnect user=${userId} reason=${reason}`);
  });
});

httpServer.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.info(
    `[socket] listening on :${PORT} (Laravel verify: ${LARAVEL_API_URL}/api/me)`,
  );
});
