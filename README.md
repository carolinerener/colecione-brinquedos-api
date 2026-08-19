# 🛍️ Colecione Brinquedos — API

API REST de um e-commerce de brinquedos educativos infantis, desenvolvida como projeto de portfólio.

> "Diversão que ensina"

---

## 🚀 Tecnologias

- Laravel 12 (PHP 8.3)
- MySQL
- Laravel Sanctum

---

## ✨ Funcionalidades

- Autenticação com token (register, login, logout)
- CRUD de categorias e subcategorias
- CRUD de produtos com slug automático e paginação
- Gerenciamento de endereços por usuário
- Criação e listagem de pedidos
- Cupons de desconto com validação
- Integração de pagamento com o Mercado Pago (Checkout Pro) e confirmação de pedidos via webhook
- Conformidade com a LGPD (exportação e exclusão de dados do usuário)
- Rotas públicas e protegidas

---

## 🔧 Como rodar localmente

```bash
git clone https://github.com/carolinerener/colecione-brinquedos-api.git
cd colecione-brinquedos-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

> Configure o `.env` com seu banco de dados MySQL e defina `DB_DATABASE=colecione_brinquedos`.
> Para a integração de pagamento, adicione suas credenciais do Mercado Pago no `.env`.

---

## 🔗 Front-end

[colecione-brinquedos-front](https://github.com/carolinerener/colecione-brinquedos-front)
