# Carrieri - Professional Education & Job Matching Platform

A comprehensive web application designed to connect candidates with professional opportunities through interactive learning, skill assessments, and job placement services. Built with Symfony, featuring AI-powered interviews, real-time messaging, and accessibility features including sign language recognition.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [Running the Application](#running-the-application)
- [Database Setup](#database-setup)
- [Development](#development)
- [Testing](#testing)
- [Deployment](#deployment)
- [API Integration](#api-integration)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

---

## Overview

Carrieri is a full-featured educational technology platform that bridges the gap between training and employment. The platform offers:

- **Interactive Learning**: Comprehensive course materials, lessons, and modules
- **Skill Assessment**: Quizzes, tests, and projects to evaluate candidate abilities
- **Mission System**: Real-world project assignments that candidates can undertake
- **AI-Powered Interviews**: Automated interview system with intelligent evaluation
- **Job Marketplace**: Browse and apply for job postings
- **Real-time Communication**: Messaging system for candidates and employers
- **Accessibility**: Sign language recognition support for inclusive learning
- **Biometric Security**: Face recognition for authentication and security verification

## Features

### For Candidates
- 📚 Access to structured courses and learning modules
- ✅ Complete quizzes and tests to measure progress
- 🎯 Take on missions and real-world projects
- 💼 Browse and apply for job opportunities
- 🤖 AI-powered mock interviews for preparation
- 💬 Direct messaging with organizations
- 📊 Track progress and achievements
- 📜 Generate and manage certificates
- 🖼️ Access workspace materials and resources
- 🪧 Submit complaints and feedback

### For Organizations
- 📋 Post job opportunities
- 👥 Manage candidates and applicants
- 📝 Create course material and missions
- 💬 Communicate directly with candidates
- 💳 Payment integration for premium features
- 📊 Analytics and reporting dashboard

### Technical Features
- 🔐 Biometric authentication (Azure Face API, AWS Rekognition)
- 🖥️ Code execution and evaluation environment
- 🎥 Real-time video communication (Jitsi integration)
- 📢 Real-time notifications via Socket.io
- 📧 Email notifications with PHPMailer
- 📱 SMS notifications with Twilio
- ☁️ AWS S3 storage integration
- 🤖 AI evaluation with Gemini API
- 🔄 Async task processing with Messenger

---

## Tech Stack

### Backend
- **Framework**: Symfony 6.4
- **Language**: PHP 8.1+
- **Database**: MySQL / PostgreSQL
- **ORM**: Doctrine ORM
- **API**: RESTful routes with Symfony Routing
- **Validation**: Symfony Validator
- **Forms**: Symfony Forms
- **Testing**: PHPUnit 11.5+

### Frontend
- **Template Engine**: Twig
- **JavaScript**: Vanilla JS + Stimulus
- **CSS**: SASS
- **Module Bundler**: Webpack with Encore
- **Icons**: FontAwesome 6.0
- **Data Visualization**: CodeMirror
- **Carousel**: Owl Carousel

### Microservices & Real-time
- **Notifications**: Socket.io (Node.js)
- **Tracking**: Custom Socket.io server
- **AI Evaluation**: FastAPI (Python)
- **Sign Language Recognition**: MediaPipe + OpenCV (Python)

### External Services
- **Payment**: Stripe
- **SMS**: Twilio
- **Face Recognition**: Azure Face API & AWS Rekognition
- **Video Conference**: Jitsi Meet
- **AI**: Google Gemini API
- **Email**: Gmail SMTP
- **Storage**: AWS S3

### DevOps
- **Containerization**: Docker & Docker Compose
- **Code Quality**: PHPStan
- **Task Runner**: Symfony Console

---

## System Requirements

### Minimum Requirements
- **PHP**: 8.1 or higher
- **Node.js**: 18+ (for frontend build tools)
- **Python**: 3.8+ (for AI components)
- **Database**: MySQL 5.7+ or PostgreSQL 12+
- **RAM**: 4GB minimum
- **Disk Space**: 5GB minimum

### Recommended Requirements
- **PHP**: 8.2+
- **Node.js**: 20 LTS
- **Python**: 3.11+
- **Database**: PostgreSQL 15+
- **RAM**: 8GB+
- **Disk Space**: 20GB+

### Optional Services
- Docker & Docker Compose (for development environment)
- AWS Account (for S3, Rekognition)
- Azure Account (for Face API)
- Stripe Account (for payments)
- Twilio Account (for SMS)

---

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd Carrieri_Webapp-main
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
npm install
```

### 4. Install Python Dependencies (Optional - for AI features)

```bash
# For AI Evaluator
cd ai_evaluator
pip install -r requirements.txt
cd ..

# For Sign Language Recognition
cd sign_language_project
pip install -r requirements.txt
cd ..

# For AI Scripts
pip install -r scripts/requirements.txt
```

### 5. Install Node.js Dependencies for Microservices

```bash
# Notification Server
cd notification-server
npm install
cd ..

# Socket Server
cd socket-server
npm install
cd ..

# Tracking Server
cd Track
npm install
cd ..
```

---

## Configuration

### 1. Environment Setup

Copy the default environment file and configure it:

```bash
# .env file is included with defaults
# Create .env.local for local overrides (git-ignored)
cp .env .env.local
```

### 2. Key Environment Variables

Edit `.env.local` with your specific configuration:

```dotenv
# Application
APP_ENV=dev
APP_SECRET=your-secret-key

# Database
DATABASE_URL="mysql://user:password@127.0.0.1:3306/carrieri"

# AI Evaluator Service
AI_EVALUATOR_URL=http://127.0.0.1:8001

# Jitsi Video Conferencing
JITSI_DOMAIN=meet.jit.si
JITSI_ROOM_PREFIX=carrieri

# Email Configuration
MAILER_DSN=smtp://user:password@smtp.gmail.com:587
MAILER_SENDER_EMAIL=your-email@example.com
MAILER_SENDER_NAME=Carrieri

# SMS (Twilio)
TWILIO_SID=your_sid
TWILIO_AUTH_TOKEN=your_token
TWILIO_PHONE_NUMBER=+xxxxxxxxxxxx

# Face Recognition (Azure)
AZURE_FACE_ENDPOINT=https://your-resource.cognitiveservices.azure.com/
AZURE_FACE_KEY=your_key
AZURE_FACE_PERSON_GROUP_ID=Carrieri-Web

# Face Recognition (AWS)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_REGION=eu-west-1
AWS_REKOGNITION_COLLECTION_ID=Carrieri-Web

# Payment (Stripe)
STRIPE_API_KEY=your_stripe_key

# Storage (AWS S3)
AWS_BUCKET=your-bucket-name
AWS_REGION2=eu-west-3

# AI (Gemini API)
GEMINI_API_KEY=your_gemini_key
```

### 3. Generate Application Secret

```bash
php bin/console secrets:generate-keys
```

### 4. Create Database and Run Migrations

```bash
# Create the database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate
```

### 5. Load Fixtures (Optional - for development data)

```bash
php bin/console doctrine:fixtures:load
```

---

## Project Structure

```
Carrieri_Webapp-main/
├── config/                          # Symfony configuration
│   ├── bundles.php                 # Registered bundles
│   ├── routes.yaml                 # Route configuration
│   ├── services.yaml               # Service definitions
│   ├── packages/                   # Package-specific configs
│   └── routes/                     # Additional route configs
│
├── src/                            # Application source code
│   ├── Controller/                 # Application controllers
│   │   ├── FrontOffice/           # Candidate-facing controllers
│   │   ├── Api/                    # API endpoints
│   │   └── DashboardController/    # Admin dashboard
│   ├── Entity/                     # Doctrine entities (data models)
│   ├── Repository/                 # Database query repositories
│   ├── Service/                    # Business logic services
│   ├── Form/                       # Symfony form types
│   ├── Security/                   # Authentication & authorization
│   ├── EventSubscriber/            # Event listeners
│   ├── Command/                    # Console commands
│   ├── Twig/                       # Custom Twig extensions
│   └── Kernel.php                  # Application kernel
│
├── templates/                      # Twig templates
│   ├── FrontOffice/               # Candidate portal interfaces
│   │   ├── home/                   # Homepage
│   │   ├── main/                   # Main pages (courses, missions, jobs)
│   │   ├── security/               # Login/registration
│   │   ├── workspace/              # Learning workspace
│   │   ├── paiement/               # Payment pages
│   │   └── asl_interview/          # Sign language interface
│   ├── BackOffice/                # Admin interfaces
│   │   └── dashboard/             # Dashboard views
│   ├── emails/                     # Email templates
│   └── public/                     # Public pages
│
├── assets/                         # Frontend assets
│   ├── js/                        # JavaScript files
│   ├── css/                       # Stylesheets
│   ├── controllers/               # Stimulus controllers
│   ├── images/                    # Image assets
│   └── bootstrap/                 # Bootstrap components
│
├── public/                         # Web root
│   ├── index.php                  # Application entry point
│   ├── build/                     # Compiled webpack assets
│   ├── uploads/                   # User uploads
│   ├── certificates/              # Generated certificates
│   └── FrontOffice/               # Frontend static files
│
├── migrations/                     # Database migrations
├── var/                           # Runtime files
│   ├── cache/                     # Application cache
│   ├── log/                       # Application logs
│   └── models/                    # Generated models
│
├── tests/                         # PHPUnit tests
├── bin/                           # Executable files
│   ├── console                   # Symfony console
│   └── phpunit                   # Test runner
│
├── notification-server/           # Notification service (Node.js)
│   ├── server-notification.js     # Main server
│   └── package.json
│
├── socket-server/                # WebSocket server (Node.js)
│   ├── server.js                 # Main server
│   └── socket-server/
│
├── Track/                        # Tracking server (Node.js)
│   ├── server-tracker.js         # Main server
│   └── package.json
│
├── ai_evaluator/                 # AI evaluation service (FastAPI)
│   ├── app.py                    # FastAPI application
│   └── requirements.txt
│
├── sign_language_project/        # Sign language recognition (Python)
│   ├── sign_language_recognizer.py
│   ├── asl_web_server.py
│   └── requirements.txt
│
├── scripts/                      # Utility scripts
│   ├── generate_offer.py         # Job offer generation
│   ├── match_score.py            # Matching algorithm
│   └── test_ollama.py            # LLM testing
│
├── doctrine.yaml                 # Doctrine configuration
├── phpstan.neon                  # PHPStan static analysis config
├── phpunit.dist.xml              # PHPUnit configuration
├── webpack.config.js             # Webpack configuration
├── package.json                  # Node.js dependencies
├── composer.json                 # PHP dependencies
├── compose.yaml                  # Docker Compose configuration
└── README.md                      # This file
```

---

## Running the Application

### Development Server

#### Using Symfony's Built-in Server

```bash
# Start the Symfony development server
symfony serve

# Or with PHP directly
php -S 127.0.0.1:8000 -t public
```

The application will be available at `http://localhost:8000`

#### Frontend Asset Building

In a separate terminal, start the webpack dev server:

```bash
npm run dev-server
```

Or watch for changes:

```bash
npm run watch
```

### Running Microservices

#### Notification Server

```bash
cd notification-server
npm start
```

#### Socket Server

```bash
cd socket-server
npm start
```

#### Tracking Server

```bash
cd Track
npm start
```

#### AI Evaluator Service

```bash
cd ai_evaluator
python -m uvicorn app:app --reload --port 8001
```

### Using Docker

Build and run the complete stack:

```bash
# Build images
docker-compose build

# Start services
docker-compose up

# Run in background
docker-compose up -d

# View logs
docker-compose logs -f
```

---

## Database Setup

### Initial Setup

```bash
# Create database
php bin/console doctrine:database:create

# Create schema
php bin/console doctrine:schema:create

# Or migrate from scratch
php bin/console doctrine:migrations:migrate
```

### Running Migrations

```bash
# View migration status
php bin/console doctrine:migrations:status

# Execute pending migrations
php bin/console doctrine:migrations:migrate

# Rollback to previous version
php bin/console doctrine:migrations:migrate prev
```

### Database Seeding (Development)

```bash
# Load fixtures (if available)
php bin/console doctrine:fixtures:load
```

### Database Backups

```bash
# Backup database
mysqldump -u root carrieri > backup.sql

# Restore database
mysql -u root carrieri < backup.sql
```

---

## Development

### Code Standards

The project uses PHPStan for static analysis:

```bash
# Run static analysis
vendor/bin/phpstan analyse

# Generate baseline
vendor/bin/phpstan analyse --generate-baseline

# Fix configuration
vendor/bin/phpstan analyse --level=8 src
```

### Creating New Entities

```bash
# Generate entity boilerplate
php bin/console make:entity

# Generate migration
php bin/console make:migration

# Execute migration
php bin/console doctrine:migrations:migrate
```

### Creating Controllers

```bash
# Generate controller
php bin/console make:controller FrontOffice/MyController
```

### Creating Forms

```bash
# Generate form type
php bin/console make:form MyFormType
```

### Clear Cache

```bash
# Clear all caches
php bin/console cache:clear

# Clear specific environment cache
php bin/console cache:clear --env=prod
```

### Asset Management

```bash
# Install web assets
php bin/console assets:install public

# Rebuild assets
npm run build
```

---

## Testing

### Running Tests

```bash
# Run all tests
php bin/phpunit

# Run specific test file
php bin/phpunit tests/Service/MyServiceTest.php

# Run with code coverage
php bin/phpunit --coverage-html=coverage

# Run only unit tests
php bin/phpunit --testsuite=Unit

# Verbose output
php bin/phpunit -v
```

### Writing Tests

Create tests in the `tests/` directory following the same structure as `src/`.

Example test file: `tests/Service/MyServiceTest.php`

```php
<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testExample(): void
    {
        $this->assertTrue(true);
    }
}
```

---

## Deployment

### Production Preparation

```bash
# Set production environment
export APP_ENV=prod

# Install dependencies (without dev packages)
composer install --no-dev --optimize-autoloader

# Build assets for production
npm run build

# Clear production cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Dump environment variables
composer dump-env prod
```

### Environment Configuration for Production

Edit `.env.prod.local` with production secrets:

```dotenv
APP_ENV=prod
APP_SECRET=your-production-secret-key
DATABASE_URL=postgresql://user:password@production-db:5432/carrieri
```

### SSL/TLS Configuration

Ensure HTTPS is enabled:

```bash
# Generate self-signed certificate (development)
mkdir -p public/certificates
openssl req -x509 -newkey rsa:4096 -keyout public/certificates/key.pem -out public/certificates/cert.pem -days 365 -nodes
```

### Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name carrieri.example.com;
    root /var/www/carrieri/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

#### Apache

```apache
<VirtualHost *:80>
    ServerName carrieri.example.com
    DocumentRoot /var/www/carrieri/public

    <Directory /var/www/carrieri/public>
        AllowOverride All
        Order Allow,Deny
        Allow from All
    </Directory>
</VirtualHost>
```

### Systemd Services (Optional)

Create service files for microservices:

```ini
# /etc/systemd/system/carrieri-notification.service
[Unit]
Description=Carrieri Notification Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/carrieri/notification-server
ExecStart=/usr/bin/node server-notification.js
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## API Integration

### Third-Party Service Integration

#### Stripe Payments

```php
// Usage in service
use Stripe\Stripe;
use Stripe\Charge;

Stripe::setApiKey(getenv('STRIPE_API_KEY'));
$charge = Charge::create([
    'amount' => 1000,
    'currency' => 'eur',
    'source' => 'tok_visa',
]);
```

#### Twilio SMS

```php
// Usage in service
use Twilio\Rest\Client;

$client = new Client(
    getenv('TWILIO_SID'),
    getenv('TWILIO_AUTH_TOKEN')
);

$client->messages->create(
    getenv('TWILIO_PHONE_NUMBER'),
    ['+1234567890'],
    ['body' => 'Your message']
);
```

#### Azure Face API

```php
// Usage in controller
// See FaceController.php for implementation
```

#### AWS Services

```php
// AWS SDK is pre-configured
// Used for S3 (file uploads) and Rekognition (face recognition)
```

#### Jitsi Meet Integration

```javascript
// Usage in JavaScript
const domain = "{{ jitsi_domain }}";
const options = {
    roomName: "{{ jitsi_room_prefix }}-" + meeting_id,
    width: '100%',
    height: '100%',
    parentNode: document.querySelector('#jitsi-container')
};
const api = new JitsiMeetExternalAPI(domain, options);
```

---

## Troubleshooting

### Common Issues

#### Database Connection Error

```
Could not connect to the Doctrine DBAL driver
```

**Solution:**
- Check DATABASE_URL in .env
- Ensure database server is running
- Verify credentials

```bash
php bin/console doctrine:database:create
```

#### Symfony Cache Issues

```bash
# Clear all caches
php bin/console cache:clear

# Clear specific environment
php bin/console cache:clear --env=dev
```

#### Webpack/Encore Build Issues

```bash
# Clean node modules
rm -rf node_modules
npm install

# Rebuild assets
npm run build
```

#### Port Already in Use

```bash
# Find process using port
netstat -tlnp | grep :8000

# Kill the process
kill -9 <PID>
```

#### Permission Issues

```bash
# Fix var directory permissions
chmod -R 777 var/

# Fix uploads directory
chmod -R 777 public/uploads/
```

#### Python Dependencies Issues

```bash
# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

### Debug Mode

Enable debug mode for detailed error messages:

```php
// In .env
APP_ENV=dev
APP_DEBUG=1
```

View debug toolbar and detailed logs:
- Web Debug Toolbar appears in bottom right
- Check logs in `var/log/`
- Use `php bin/console server:start` for built-in server

### Logs

```bash
# View recent logs
tail -f var/log/dev.log

# Filter logs
grep "ERROR" var/log/dev.log
```

---

## Contributing

### Code Style

- Follow PSR-12 PHP coding standards
- Use type hints for all function parameters and returns
- Write meaningful variable names
- Add comments for complex logic

### Commit Messages

```
[FEATURE] Add new mission assignment system
[BUG] Fix authentication flow in ASL interview
[DOCS] Update README with deployment guides
[TEST] Add unit tests for payment service
```

### Pull Request Process

1. Create feature branch: `git checkout -b feature/new-feature`
2. Make changes and test
3. Commit with descriptive messages
4. Run static analysis: `vendor/bin/phpstan analyse`
5. Run tests: `php bin/phpunit`
6. Push to origin: `git push origin feature/new-feature`
7. Create pull request with description

---

## Additional Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [Twig Template Engine](https://twig.symfony.com/)
- [PHP Standards](https://www.php-fig.org/psr/)

## License

This project is proprietary. All rights reserved.

## Support

For issues, questions, or submissions, please contact the development team.

---

**Last Updated**: May 2026
**Version**: 1.0.0

