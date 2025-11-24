# 🚀 E-Commerce Backend API (Laravel + Sanctum)

**Early Development Documentation**

This repository contains the backend API for our e-commerce platform built with **Laravel** and authenticated using **Sanctum**.
The API is consumed by two separate frontend applications:

* **Next.js**
* **Angular**

The backend is designed to be modular, secure, and easily scalable.

---

## 📦 Tech Stack

| Layer                      | Technology                                 |
| -------------------------- | ------------------------------------------ |
| Backend Framework          | Laravel 11                                 |
| API Authentication         | Laravel Sanctum                            |
| Database                   | MySQL                                      |
| ORM                        | Eloquent                                   |
| Caching / Queue (optional) | Redis                                      |
| Storage                    | Local / S3 / Cloudflare R2                 |
| API Docs                   | Laravel Swagger (L5-Swagger) *(planned)*   |

---

## 🗂 Project Structure (Laravel Standard)

```
/app
 ├─ Http/
 │   ├─ Controllers/
 │   ├─ Middleware/
 │   ├─ Requests/
 │   └─ Resources/
 ├─ Models/
 ├─ Providers/
 ├─ Services/
 └─ Helpers/
├─ routes/
│   └─ api.php
├─ database/
│   ├─ migrations/
│   ├─ seeders/
│   └─ factories/
```

---

## 🔐 Authentication Flow (Sanctum)

Sanctum supports two approaches.
For this API we use **Token Authentication**, which works for multi-domain clients like Next.js & Angular.

### Login Flow:

1. Frontend sends email & password
2. Backend verifies credentials
3. Backend responds with:

   * access token
   * user data
4. Frontend stores token securely (local storage / secure storage)
5. All further requests include the token in the Authorization header:

```
Authorization: Bearer <token>
```

---

## 📘 API Overview (Initial Draft)

All routes are prefixed with:

```
/api/v1/
```

---

### **Auth**

| Method | Endpoint                | Description                    |
| ------ | ----------------------- | ------------------------------ |
| POST   | `/api/v1/auth/register` | Register new user (customer)   |
| POST   | `/api/v1/auth/login`    | Login + generate Sanctum token |
| POST   | `/api/v1/auth/logout`   | Revoke token                   |
| GET    | `/api/v1/auth/user`     | Get authenticated user         |

---

### **Products**

| Method | Endpoint                | Description    | Access |
| ------ | ----------------------- | -------------- | ------ |
| GET    | `/api/v1/products`      | List products  | Public |
| GET    | `/api/v1/products/{id}` | Single product | Public |
| POST   | `/api/v1/products`      | Create product | Admin  |
| PUT    | `/api/v1/products/{id}` | Update product | Admin  |
| DELETE | `/api/v1/products/{id}` | Delete product | Admin  |

---

### **Categories**

| Method | Endpoint                  | Description     | Access |
| ------ | ------------------------- | --------------- | ------ |
| GET    | `/api/v1/categories`      | List categories | Public |
| POST   | `/api/v1/categories`      | Create category | Admin  |
| PUT    | `/api/v1/categories/{id}` | Update category | Admin  |
| DELETE | `/api/v1/categories/{id}` | Delete category | Admin  |

---

### **Cart**

| Method | Endpoint                       | Description      | Access   |
| ------ | ------------------------------ | ---------------- | -------- |
| GET    | `/api/v1/cart`                 | Get cart         | Customer |
| POST   | `/api/v1/cart/add`             | Add item to cart | Customer |
| PUT    | `/api/v1/cart/update/{itemId}` | Update quantity  | Customer |
| DELETE | `/api/v1/cart/{itemId}`        | Remove item      | Customer |

---

### **Orders**

| Method | Endpoint               | Description         | Access   |
| ------ | ---------------------- | ------------------- | -------- |
| POST   | `/api/v1/orders`       | Create order        | Customer |
| GET    | `/api/v1/orders`       | Customer order list | Customer |
| GET    | `/api/v1/orders/{id}`  | Single order detail | Customer |
| GET    | `/api/v1/admin/orders` | List all orders     | Admin    |

---

### **Admin Routes (Optional separate grouping)**

For Angular admin dashboard:

| Method | Endpoint                      | Description           |
| ------ | ----------------------------- | --------------------- |
| GET    | `/api/v1/admin/products`      | Admin product list    |
| POST   | `/api/v1/admin/products`      | Create product        |
| PUT    | `/api/v1/admin/products/{id}` | Update product        |
| DELETE | `/api/v1/admin/products/{id}` | Delete product        |
| GET    | `/api/v1/admin/orders`        | View all orders       |
| GET    | `/api/v1/admin/users`         | List users (optional) |

---

## 🧪 Local Development Setup

```bash
git clone https://github.com/fathursyh/ecommerce-api
cd ecommerce-api

composer install
cp .env.example .env

php artisan key:generate

# Migrate database
php artisan migrate --seed

# Start dev server
composer run dev
```

Example `.env` configuration:

```
APP_NAME=EcommerceAPI
APP_ENV=local
APP_KEY=base64:xxxx
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:4200
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
```

---

## 🛠 Roadmap

* [x] User registration & login
* [ ] Sanctum token abilities for role-based access
* [ ] Product & category modules
* [ ] Cart + checkout logic
* [ ] Order lifecycle
* [ ] Admin endpoint separation
* [ ] API documentation (Swagger/L5-Swagger)
* [ ] Seeders for development data
* [ ] Rate limiting configuration

---

## 🤝 Contribution Guide

* Use feature branches (`feature/products-module`)
* Maintain consistent code formatting
* PRs must include:

  * description of the change
  * testing steps
  * API contract updates if needed

---

