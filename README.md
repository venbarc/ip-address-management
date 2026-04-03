# IP Address Management System

A web-based IP Address Management (IPAM) solution built with a microservices architecture. Authenticated users can record, manage, and audit IPv4/IPv6 addresses with role-based access control.

---

## Architecture

```
Browser
  └── Frontend (React + TypeScript)      host :5173
        └── Gateway Service (Laravel)    host :8000
              ├── Auth Service (Laravel) internal (auth-service:8000)
              └── IP Service (Laravel)   internal (ip-service:8000)
                    └── MySQL            internal :3306
```

| Service | Role | Port |
|---|---|---|
| `frontend` | React + Vite UI | **5173** (host) |
| `gateway` | API gateway, auth enforcement, request routing | **8000** (host) |
| `auth-service` | JWT issuance, token refresh, session management | internal only |
| `ip-service` | IP address CRUD, role-based authorization, audit logs | internal only |
| `mysql` | Shared database host — 3 independent databases | internal only |

---

## Prerequisites

- [Docker](https://www.docker.com/get-started) and Docker Compose v2
- Ports `5173` (frontend) and `8000` (gateway) available on your machine

---

## Quick Start (Docker)

### 1. Clone the repository

```bash
git clone <your-repo-url>
cd ip-address-management
```

### 2. Configure environment secrets

Create a `.env` file at the project root:

```bash
cp .env.example .env
```

Edit `.env` and set the required secrets:

```env
JWT_SECRET=replace-with-a-long-random-string-min-32-chars
INTERNAL_SERVICE_SECRET=replace-with-another-long-random-string
```

> **Important:** `JWT_SECRET` and `INTERNAL_SERVICE_SECRET` must be set before starting. Both must be the same `INTERNAL_SERVICE_SECRET` value across all services (injected via docker-compose).

### 3. Start all services

```bash
docker compose up --build
```

This will:
- Start MySQL and create the 3 databases (`ipam_auth`, `ipam_ip`, `ipam_gateway`)
- Run database migrations for each service
- Seed the default users (auth-service only)
- Start all services

### 4. Open the app

Visit [http://localhost:5173](http://localhost:5173)

---

## Default Accounts

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@example.com | password123 |
| Regular User | user@example.com | password123 |

---

## Manual Setup (Without Docker)

### Requirements

- PHP 8.3+ with extensions: `pdo_mysql`, `mbstring`, `bcmath`, `xml`
- Composer 2
- Node.js 20+
- MySQL 8.0+

### 1. Create databases

```sql
CREATE DATABASE ipam_auth;
CREATE DATABASE ipam_ip;
CREATE DATABASE ipam_gateway;
```

### 2. Configure each service

Copy and edit `.env` for each service:

```bash
# Repeat for auth-service, ip-service, gateway
cd services/auth-service
cp .env.example .env   # edit DB credentials, JWT_SECRET, INTERNAL_SERVICE_SECRET
```

Key values to set in each `.env`:

**auth-service**
```env
DB_DATABASE=ipam_auth
JWT_SECRET=your-secret-here
INTERNAL_SERVICE_SECRET=shared-secret
```

**ip-service**
```env
DB_DATABASE=ipam_ip
INTERNAL_SERVICE_SECRET=shared-secret
```

**gateway**
```env
DB_DATABASE=ipam_gateway
INTERNAL_SERVICE_SECRET=shared-secret
AUTH_SERVICE_URL=http://localhost:8001
IP_SERVICE_URL=http://localhost:8002
```

### 3. Install & migrate each service

```bash
# Run in each service directory (auth-service, ip-service, gateway)
composer install
php artisan key:generate
php artisan migrate
# auth-service only:
php artisan db:seed
```

### 4. Start the services (4 terminals)

```bash
# Terminal 1
cd services/auth-service && php artisan serve --port=8001

# Terminal 2
cd services/ip-service && php artisan serve --port=8002

# Terminal 3
cd services/gateway && php artisan serve --port=8000

# Terminal 4
cd frontend && npm install && npm run dev
```

Visit [http://localhost:5173](http://localhost:5173)

---

## Features

- **Authentication** — JWT-based login with automatic token refresh
- **IP Management** — Add, edit IPv4/IPv6 addresses with labels and optional comments
- **Role-based access** — Regular users manage their own records; super-admins manage all
- **Immutable audit logs** — Every change, login, and logout is permanently recorded
- **Audit Dashboard** — Super-admin exclusive view of all system events with filters
- **Distributed tracing** — Correlation IDs across all services

---

## Project Structure

```
ip-address-management/
├── docker-compose.yml
├── docker/
│   └── mysql/
│       └── init.sql          # Creates all 3 databases
├── frontend/                 # React + TypeScript (Vite)
│   ├── Dockerfile
│   ├── nginx.conf
│   └── src/
├── services/
│   ├── auth-service/         # Laravel — JWT, sessions, users
│   │   ├── Dockerfile
│   │   └── entrypoint.sh
│   ├── ip-service/           # Laravel — IP CRUD, authorization
│   │   ├── Dockerfile
│   │   └── entrypoint.sh
│   └── gateway/              # Laravel — API gateway, routing
│       ├── Dockerfile
│       └── entrypoint.sh
└── README.md
```
