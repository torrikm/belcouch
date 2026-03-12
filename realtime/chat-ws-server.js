const http = require("http");
const crypto = require("crypto");
const { WebSocketServer } = require("ws");

const PORT = Number(process.env.CHAT_WS_PORT || 8081);
const SHARED_SECRET =
	process.env.CHAT_WS_SHARED_SECRET || "belcouch_chat_secret_change_me";

const users = new Map(); // userId -> Set<ws>

function safeJsonParse(text) {
	try {
		return JSON.parse(text);
	} catch (error) {
		return null;
	}
}

function verifyClientAuth(searchParams) {
	const userId = Number(searchParams.get("user_id") || 0);
	const ts = Number(searchParams.get("ts") || 0);
	const sig = String(searchParams.get("sig") || "");
	if (!userId || !ts || !sig) {
		return null;
	}

	const now = Math.floor(Date.now() / 1000);
	if (Math.abs(now - ts) > 3600) {
		return null;
	}

	const expected = crypto
		.createHmac("sha256", SHARED_SECRET)
		.update(`${userId}|${ts}`)
		.digest("hex");

	if (expected !== sig) {
		return null;
	}

	return userId;
}

function sendToUser(userId, payload) {
	const sockets = users.get(Number(userId));
	if (!sockets || sockets.size === 0) {
		return;
	}

	const message = JSON.stringify(payload);
	for (const ws of sockets) {
		if (ws.readyState === ws.OPEN) {
			ws.send(message);
		}
	}
}

function getOnlineUserIds() {
	return Array.from(users.keys());
}

function broadcast(payload, excludeUserId = 0) {
	const message = JSON.stringify(payload);
	for (const [userId, sockets] of users.entries()) {
		if (Number(userId) === Number(excludeUserId)) {
			continue;
		}

		for (const ws of sockets) {
			if (ws.readyState === ws.OPEN) {
				ws.send(message);
			}
		}
	}
}

const server = http.createServer((req, res) => {
	if (req.method === "POST" && req.url === "/emit") {
		const secret = req.headers["x-chat-secret"];
		if (secret !== SHARED_SECRET) {
			res.writeHead(403, { "Content-Type": "application/json" });
			res.end(JSON.stringify({ ok: false, error: "forbidden" }));
			return;
		}

		let raw = "";
		req.on("data", (chunk) => {
			raw += chunk;
			if (raw.length > 1_000_000) {
				req.destroy();
			}
		});
		req.on("end", () => {
			const payload = safeJsonParse(raw);
			if (!payload || !Array.isArray(payload.user_ids)) {
				res.writeHead(400, { "Content-Type": "application/json" });
				res.end(JSON.stringify({ ok: false, error: "invalid_payload" }));
				return;
			}

			const event = payload.event || "chat:update";
			const eventPayload = payload.payload || {};
			payload.user_ids.forEach((userId) => {
				sendToUser(userId, {
					event,
					payload: eventPayload,
				});
			});

			res.writeHead(200, { "Content-Type": "application/json" });
			res.end(JSON.stringify({ ok: true }));
		});
		return;
	}

	res.writeHead(200, { "Content-Type": "application/json" });
	res.end(JSON.stringify({ ok: true, service: "chat-ws" }));
});

const wss = new WebSocketServer({ noServer: true });

wss.on("connection", (ws, req, userId) => {
	const normalizedUserId = Number(userId);
	const wasOnline = users.has(normalizedUserId) && users.get(normalizedUserId).size > 0;
	if (!users.has(normalizedUserId)) {
		users.set(normalizedUserId, new Set());
	}
	users.get(normalizedUserId).add(ws);

	ws.send(
		JSON.stringify({
			event: "presence:snapshot",
			payload: {
				user_ids: getOnlineUserIds(),
			},
		})
	);

	if (!wasOnline) {
		broadcast(
			{
				event: "presence:update",
				payload: {
					user_id: normalizedUserId,
					is_online: true,
				},
			},
			normalizedUserId
		);
	}

	ws.on("close", () => {
		const set = users.get(normalizedUserId);
		if (!set) {
			return;
		}
		set.delete(ws);
		if (set.size === 0) {
			users.delete(normalizedUserId);
			broadcast({
				event: "presence:update",
				payload: {
					user_id: normalizedUserId,
					is_online: false,
				},
			});
		}
	});
});

server.on("upgrade", (req, socket, head) => {
	const url = new URL(req.url, `http://${req.headers.host}`);
	if (url.pathname !== "/ws") {
		socket.destroy();
		return;
	}

	const userId = verifyClientAuth(url.searchParams);
	if (!userId) {
		socket.destroy();
		return;
	}

	wss.handleUpgrade(req, socket, head, (ws) => {
		wss.emit("connection", ws, req, userId);
	});
});

server.listen(PORT, () => {
	console.log(`chat-ws server listening on :${PORT}`);
});
