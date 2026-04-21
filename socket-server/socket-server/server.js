const express = require('express');
const app = express();
const http = require('http');
const server = http.createServer(app);
const io = require('socket.io')(server, {
    cors: {
        origin: "http://localhost:8000",
        methods: ["GET", "POST"]
    }
});

io.on('connection', (socket) => {
    console.log('✅ Utilisateur connecté:', socket.id);

    socket.on('join-conversation', (conversationId) => {
        socket.join(`conv-${conversationId}`);
        console.log(`📌 Utilisateur a rejoint la conversation ${conversationId}`);
    });

    socket.on('leave-conversation', (conversationId) => {
        socket.leave(`conv-${conversationId}`);
        console.log(`🚪 Utilisateur a quitté la conversation ${conversationId}`);
    });

    socket.on('send-message', (data) => {
        console.log(`💬 Message reçu: ${data.contenu}`);
        socket.emit('new-message', { ...data, est_moi: true });
        socket.to(`conv-${data.conversation_id}`).emit('new-message', { ...data, est_moi: false });
    });

    socket.on('disconnect', () => {
        console.log('❌ Utilisateur déconnecté:', socket.id);
    });
});

const PORT = 3001;
server.listen(PORT, () => {
    console.log(`🚀 Serveur Socket.io démarré sur http://localhost:${PORT}`);
});