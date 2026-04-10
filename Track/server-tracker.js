const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());

const server = http.createServer(app);
const io = socketIo(server, {
    cors: { origin: "*", methods: ["GET", "POST"] }
});

// Stockage des sessions actives
const activeSessions = new Map();

console.log('🎥 Live Session Tracker - Mode Incognito');
console.log('=========================================\n');

io.on('connection', (socket) => {
    console.log(`🔌 Client connecté: ${socket.id}`);

    // 1. Candidat démarre une session
    socket.on('session:start', (data) => {
        const { missionId, candidatId, candidatName, missionTitle } = data;
        const sessionId = `session_${missionId}_${candidatId}`;

        const session = {
            socketId: socket.id,
            missionId,
            candidatId,
            candidatName,
            missionTitle,
            status: 'active',
            startTime: Date.now(),
            lastHeartbeat: Date.now(),
            totalTime: 0,
            events: []
        };

        activeSessions.set(sessionId, session);
        socket.join(sessionId);

        console.log(`📊 [ACTIVE] ${candidatName} a commencé la mission "${missionTitle}"`);

        // Informer tous les recruteurs
        io.emit('session:started', {
            sessionId,
            candidatId,
            candidatName,
            missionTitle,
            startTime: session.startTime
        });

        // Envoyer la liste mise à jour
        sendActiveSessions();
    });

    // 2. Heartbeat - le candidat est toujours là
    socket.on('session:heartbeat', (data) => {
        const { missionId, candidatId } = data;
        const sessionId = `session_${missionId}_${candidatId}`;
        const session = activeSessions.get(sessionId);

        if (session) {
            const now = Date.now();
            const elapsed = now - session.lastHeartbeat;
            session.lastHeartbeat = now;
            session.totalTime += elapsed;

            // Mettre à jour tous les 30 secondes
            io.emit('session:heartbeat', {
                sessionId,
                totalTime: session.totalTime,
                lastHeartbeat: now
            });
        }
    });

    // 3. Candidat change de page ou perd le focus
    socket.on('session:focus', (data) => {
        const { missionId, candidatId, hasFocus } = data;
        const sessionId = `session_${missionId}_${candidatId}`;

        io.emit('session:focus', {
            sessionId,
            hasFocus,
            timestamp: Date.now()
        });

        console.log(`👁️ ${hasFocus ? 'Focus' : 'Perdu focus'} - Session ${sessionId}`);
    });

    // 4. Candidat exécute du code
    socket.on('session:execute', (data) => {
        const { missionId, candidatId, success, error } = data;
        const sessionId = `session_${missionId}_${candidatId}`;

        io.emit('session:execute', {
            sessionId,
            success,
            error: error || null,
            timestamp: Date.now()
        });

        console.log(`⚡ Exécution - Session ${sessionId} - ${success ? 'Succès' : 'Erreur'}`);
    });

    // 5. Candidat termine/ferme la session
    socket.on('session:end', (data) => {
        const { missionId, candidatId } = data;
        const sessionId = `session_${missionId}_${candidatId}`;
        const session = activeSessions.get(sessionId);

        if (session) {
            const totalDuration = Date.now() - session.startTime;

            console.log(`📊 [END] ${session.candidatName} a terminé - Durée: ${Math.floor(totalDuration / 1000)}s`);

            io.emit('session:ended', {
                sessionId,
                candidatId,
                candidatName: session.candidatName,
                missionTitle: session.missionTitle,
                totalDuration,
                totalTimeSpent: session.totalTime
            });

            activeSessions.delete(sessionId);
            sendActiveSessions();
        }
    });

    // 6. Recruteur demande les sessions actives
    socket.on('sessions:get', () => {
        sendActiveSessions(socket);
    });

    // 7. Recruteur demande les détails d'une session
    socket.on('session:details', (data) => {
        const { sessionId } = data;
        const session = activeSessions.get(sessionId);

        if (session) {
            socket.emit('session:details', {
                sessionId,
                candidatName: session.candidatName,
                missionTitle: session.missionTitle,
                startTime: session.startTime,
                currentDuration: Date.now() - session.startTime,
                activeTime: session.totalTime,
                status: session.status
            });
        }
    });

    // 8. Déconnexion
    socket.on('disconnect', () => {
        // Trouver et nettoyer la session du candidat déconnecté
        for (const [sessionId, session] of activeSessions.entries()) {
            if (session.socketId === socket.id) {
                console.log(`❌ Déconnexion inattendue: ${session.candidatName}`);

                io.emit('session:disconnected', {
                    sessionId,
                    candidatName: session.candidatName,
                    missionTitle: session.missionTitle,
                    totalDuration: Date.now() - session.startTime
                });

                activeSessions.delete(sessionId);
                sendActiveSessions();
                break;
            }
        }
    });
});

function sendActiveSessions(targetSocket = null) {
    const sessions = Array.from(activeSessions.values()).map(s => ({
        sessionId: `session_${s.missionId}_${s.candidatId}`,
        candidatId: s.candidatId,
        candidatName: s.candidatName,
        missionTitle: s.missionTitle,
        startTime: s.startTime,
        currentDuration: Date.now() - s.startTime,
        activeTime: s.totalTime,
        status: s.status
    }));

    const event = 'sessions:list';
    const data = { sessions, count: sessions.length, timestamp: Date.now() };

    if (targetSocket) {
        targetSocket.emit(event, data);
    } else {
        io.emit(event, data);
    }
}

// Route pour le statut
app.get('/status', (req, res) => {
    res.json({
        activeSessions: activeSessions.size,
        sessions: Array.from(activeSessions.values()).map(s => ({
            candidatName: s.candidatName,
            missionTitle: s.missionTitle,
            duration: Math.floor((Date.now() - s.startTime) / 1000)
        }))
    });
});

const PORT = 3001;
server.listen(PORT, () => {
    console.log(`🚀 Session Tracker sur http://localhost:${PORT}`);
    console.log(`📊 Statut: http://localhost:${PORT}/status\n`);
});