# Real-Time Chat App

## Prerequisites

- Docker and Docker Compose
- Composer
- PHP8.4 or higher (v8.4 is provided through Docker)
- PostgreSQL (provided through Docker)
- NodeJS and NPM (provided through Docker)

## Installation

### Clone the repository

```bash
git clone https://github.com/sir-crashalot/imagine-chat
cd imagine-chat
```

### Create environment file

```bash
cp .env.example .env
```

### Obtain GitHub OAuth2 credentials

1. Go to: https://github.com/settings/developers
2. Click **"New OAuth App"**
3. Fill in:
   - **Application name**: Chat Application
   - **Homepage URL**: `http://localhost:8080`
   - **Authorization callback URL**: `http://localhost:8080/auth/github/callback`
4. Click **"Register application"**
5. Copy the **Client ID** and click **"Generate a new client secret"**
6. Set both in your `.env` file:
```env
GITHUB_CLIENT_ID=paste_your_client_id_here
GITHUB_CLIENT_SECRET=paste_your_client_secret_here
GITHUB_REDIRECT_URI=http://localhost:8080/auth/github/callback
```

### Configure other environment variables in .env

```env
APP_NAME="Chat Application"
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=chat_app
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

### Build and start containers

```bash
docker-compose up -d --build
```

### Install dependencies

```bash
docker-compose exec php composer install
docker-compose exec php npm install
```

### Generate application key

```bash
docker-compose exec php php artisan key:generate
```

### Run database migrations

```bash
docker-compose exec php php artisan migrate
```

This creates:
- `users` table (with GitHub OAuth fields)
- `messages` table
- PostgreSQL trigger for real-time notifications

### Build frontend assets

```bash
docker-compose exec php npm run build
```

### Ensure web user has permissions to write logs

```bash
docker-compose exec php chown -R www-data:www-data storage bootstrap/cache
```

### Access the application

You can now access the application by navigating to http://localhost:8080.

## Project Structure

```
imagine-chat/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php      # GitHub OAuth handling
│   │   │   ├── MessageController.php   # Message CRUD operations
│   │   │   └── SSEController.php       # Server-Sent Events endpoint
│   │   └── Requests/
│   │       └── StoreMessageRequest.php # Message validation
│   └── Models/
│       ├── User.php                     # User model with GitHub fields
│       └── Message.php                  # Message model
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_users_table.php
│       ├── 2026_01_11_211657_create_messages_table.php
│       └── 2026_01_12_085154_create_pg_notify_trigger_for_messages.php
├── docker/
│   ├── nginx/
│   │   └── default.conf                 # Nginx configuration for SSE
│   └── php/
│       └── Dockerfile                   # PHP 8.4-FPM with PostgreSQL
├── resources/
│   ├── views/
│   │   ├── welcome.blade.php           # Landing page with GitHub login
│   │   └── chat.blade.php              # Chat interface
│   ├── css/
│   └── js/
├── routes/
│   └── web.php                          # Application routes
├── docker-compose.yml                   # Docker Compose configuration
└── README.md                            # This file
```

## Architecture

### Realtime communication flow

1. **Message Creation**:
   - User sends a message via POST `/api/messages`
   - MessageController validates and saves to database
   - PostgreSQL trigger fires on INSERT
   - Trigger sends NOTIFY event with message data (JSON)

2. **Real-Time Delivery**:
   - SSE endpoint (`/api/events`) maintains long-lived connection
   - SSEController connects to PostgreSQL using `pg_connect()`
   - Controller listens for NOTIFY events using `LISTEN new_message`
   - When notification received, fetches full message with user data
   - Sends SSE event to connected clients
   - Frontend JavaScript receives event and updates UI

3. **Frontend Connection**:
   - JavaScript EventSource connects to `/api/events`
   - Receives `message` events when new messages arrive
   - Automatically reconnects on connection loss
   - Displays connection status indicator

### Database schema

**users table**:
- `id` (Primary Key)
- `github_id` (Unique, GitHub user ID)
- `username` (GitHub username/login)
- `email` (GitHub email, nullable)
- `github_avatar_url` (GitHub profile picture URL, nullable)
- `remember_token`
- `created_at` (timestamp of when the user was created)
- `updated_at` (timestamp of when the user was last updated)

**messages table**:
- `id` (Primary Key)
- `user_id` (Foreign Key to users)
- `content` (Text, message content)
- `created_at` (timestamp of when the message was created)
- `updated_at` (timestamp of when the message was last updated)

**PostgreSQL Trigger**:
- `notify_new_message()` function - Sends NOTIFY with message JSON
- `notify_new_message_trigger` - Fires on INSERT to messages table

### Security measures taken

1. **XSS Prevention**:
   - All user-generated content escaped using `e()` helper
   - HTML entities properly encoded before display
   - Frontend uses textContent for dynamic content where possible

2. **SQL Injection Protection**:
   - Eloquent ORM with parameterized queries
   - No raw SQL queries with user input
   - Database migrations use Laravel Schema Builder

3. **CSRF Protection**:
   - Laravel CSRF tokens on all forms
   - AJAX requests include CSRF token in headers
   - CSRF validation on POST routes

4. **Authentication**:
   - GitHub OAuth2 for secure authentication
   - Laravel session-based authentication
   - Protected routes require authentication middleware

## API endpoints

### Public routes
- `GET /` - Landing page
- `GET /auth/github` - Redirect to GitHub OAuth
- `GET /auth/github/callback` - GitHub OAuth callback

### Protected routes (require authentication)
- `GET /chat` - Chat interface page
- `GET /api/messages` - Get all messages (chat history)
- `POST /api/messages` - Create new message
- `GET /api/events` - Server-Sent Events stream for real-time updates
- `POST /logout` - Logout user

## Known limitations / possible improvements

1. **Scalability**:
   - While the solution includes Docker containers for quick deployment, scalability issues may appear in PostgreSQL connections
   - Current implementation uses single PostgreSQL connection per SSE client
   - For high-scale deployment, we should consider:
     - Redis pub/sub for message broadcasting
     - WebSocket alternative (Laravel Echo + Pusher/Soketi)

2. **Message length**:
   - Maximum message length: 5000 characters (configurable in StoreMessageRequest)
   - Longer messages will be rejected with validation error

3. **Private messages**:
   - All users currently participate in a global chat room
   - One-on-one conversations or small dedicated group chats would improve user experience

4. **Message reactions and file uploads**:
   - There is no built in way to react to messages or share images, which could improve how users experience the application

5. **Rate limiting and pagination**:
   - Implement rate limiting for sending messages and load them in pages to improve performance

6. **Search function**:
   - Messages can not be searched yet

## Tech stack

- **Laravel Framework**: https://laravel.com
- **Laravel Socialite**: https://laravel.com/docs/socialite
- **PostgreSQL**: https://www.postgresql.org
- **Server-Sent Events**: https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events
- **Docker**: https://docs.docker.com/
- **nginx**: https://docs.nginx.com/
- **PHP**: https://www.php.net/manual/en/index.php
