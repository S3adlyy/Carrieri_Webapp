// C:\xampp\htdocs\Carrieri\test\notification-server\server-notification.js
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Stockage des connexions des candidats
const connectedCandidates = new Map(); // candidatId -> socketId

console.log('🔔 Système de notifications en temps réel');
console.log('=========================================\n');

io.on('connection', (socket) => {
    console.log(`🔌 Nouvelle connexion: ${socket.id}`);

    // 1. Candidat s'enregistre pour les notifications
    socket.on('candidate:register', (data) => {
        const { candidatId, candidatName } = data;

        connectedCandidates.set(candidatId, {
            socketId: socket.id,
            candidatName,
            connectedAt: Date.now()
        });

        console.log(`👨‍💻 [CANDIDAT] ${candidatName} (${candidatId}) enregistré pour les notifications`);

        socket.emit('notification:registered', {
            success: true,
            message: 'Vous recevrez les notifications en temps réel'
        });
    });

    // 2. Recruteur envoie une notification de mission acceptée
    socket.on('recruiter:mission:accepted', (data) => {
        const { candidatId, missionTitle, score, recruiterName } = data;

        const notification = {
            id: Date.now(),
            type: 'mission_accepted',
            title: '🎉 Mission acceptée !',
            message: `Votre mission "${missionTitle}" a été acceptée avec un score de ${score}%`,
            details: {
                missionTitle,
                score,
                recruiterName,
                acceptedAt: new Date().toISOString()
            },
            action: {
                type: 'view_results',
                url: `/candidat/rendu-mission/mes-resultats`
            },
            read: false,
            timestamp: Date.now()
        };

        // Envoyer la notification au candidat
        const candidate = connectedCandidates.get(candidatId);
        if (candidate) {
            io.to(candidate.socketId).emit('notification:new', notification);
            console.log(`📨 [NOTIFICATION] Mission acceptée envoyée à ${candidate.candidatName}`);
        } else {
            console.log(`⚠️ [CANDIDAT] ${candidatId} non connecté, notification en attente`);
            // Stocker pour envoi ultérieur
        }
    });

    // 3. Recruteur envoie une notification d'entretien planifié
    socket.on('recruiter:interview:scheduled', (data) => {
        const { candidatId, interviewDate, jitsiLink, interviewType, recruiterName } = data;

        const notification = {
            id: Date.now(),
            type: 'interview_scheduled',
            title: '📅 Entretien planifié !',
            message: `Un entretien ${interviewType} a été planifié pour le ${interviewDate}`,
            details: {
                interviewDate,
                jitsiLink,
                interviewType,
                recruiterName
            },
            action: {
                type: 'join_interview',
                url: jitsiLink,
                buttonText: 'Rejoindre l\'entretien'
            },
            read: false,
            timestamp: Date.now()
        };

        const candidate = connectedCandidates.get(candidatId);
        if (candidate) {
            io.to(candidate.socketId).emit('notification:new', notification);
            console.log(`📨 [NOTIFICATION] Entretien planifié envoyé à ${candidate.candidatName}`);
        }
    });

    // 4. Candidat demande ses notifications non lues
    socket.on('notification:get-unread', (data) => {
        const { candidatId } = data;
        // Ici vous pouvez récupérer depuis la base de données
        // Pour l'instant, on renvoie une liste vide
        socket.emit('notification:unread-list', { notifications: [] });
    });

    // 5. Candidat marque une notification comme lue
    socket.on('notification:mark-read', (data) => {
        const { candidatId, notificationId } = data;
        // Stocker en base
        console.log(`📖 Notification ${notificationId} marquée comme lue par candidat ${candidatId}`);
    });

    // 6. Déconnexion
    socket.on('disconnect', () => {
        // Nettoyer la connexion
        for (const [candidatId, info] of connectedCandidates.entries()) {
            if (info.socketId === socket.id) {
                console.log(`❌ [CANDIDAT] ${info.candidatName} déconnecté`);
                connectedCandidates.delete(candidatId);
                break;
            }
        }
    });
});

// API REST pour envoyer des notifications depuis Symfony
app.post('/api/notify/mission-accepted', (req, res) => {
    const { candidatId, missionTitle, score, recruiterName } = req.body;

    const notification = {
        id: Date.now(),
        type: 'mission_accepted',
        title: '🎉 Mission acceptée !',
        message: `Votre mission "${missionTitle}" a été acceptée avec un score de ${score}%`,
        details: { missionTitle, score, recruiterName },
        action: { type: 'view_results', url: '/candidat/rendu-mission/mes-resultats' },
        timestamp: Date.now()
    };

    const candidate = connectedCandidates.get(candidatId);
    if (candidate) {
        io.to(candidate.socketId).emit('notification:new', notification);
        res.json({ success: true, message: 'Notification envoyée' });
    } else {
        res.json({ success: false, message: 'Candidat non connecté' });
    }
});

app.post('/api/notify/interview-scheduled', (req, res) => {
    const { candidatId, interviewDate, jitsiLink, interviewType, recruiterName } = req.body;

    const notification = {
        id: Date.now(),
        type: 'interview_scheduled',
        title: '📅 Entretien planifié !',
        message: `Un entretien ${interviewType} a été planifié`,
        details: { interviewDate, jitsiLink, interviewType, recruiterName },
        action: { type: 'join_interview', url: jitsiLink, buttonText: 'Rejoindre l\'entretien' },
        timestamp: Date.now()
    };

    const candidate = connectedCandidates.get(candidatId);
    if (candidate) {
        io.to(candidate.socketId).emit('notification:new', notification);
        res.json({ success: true, message: 'Notification envoyée' });
    } else {
        res.json({ success: false, message: 'Candidat non connecté' });
    }
});

app.get('/api/status', (req, res) => {
    res.json({
        connectedCandidates: connectedCandidates.size,
        candidates: Array.from(connectedCandidates.values()).map(c => ({
            name: c.candidatName,
            connectedAt: c.connectedAt
        }))
    });
});

const PORT = 3002;
server.listen(PORT, () => {
    console.log(`\n🚀 Serveur de notifications sur http://localhost:${PORT}`);
    console.log(`📡 WebSocket prêt à recevoir des connexions`);
    console.log(`📊 Statut: http://localhost:${PORT}/api/status\n`);
});