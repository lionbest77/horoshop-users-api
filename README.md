# Опис проєкту та тестування

Проєкт — невеликий API користувачів на Symfony 7.4. Авторизація виконується за статичними токенами (Bearer), без `/auth/login`.

## Швидкий старт

1. Встановити залежності:
   - `composer install`
2. Створити БД і застосувати міграції:
   - `bin/console doctrine:database:create`
   - `bin/console doctrine:migrations:migrate`
3. Запустити сервер:
   - `symfony serve` или `php -S 127.0.0.1:8000 -t public`

## Авторизація

Використовуються два тестові токени (див. `.env` / `.env.dev`):

- `ROOT_API_TOKEN` → користувач `root` (роль `ROLE_ROOT`)
- `USER_API_TOKEN` → користувач `user` (роль `ROLE_USER`)

Передаються в заголовку:

```
Authorization: Bearer <token>
```

## Ендпоінти

### GET `/v1/api/users/{id}`
Повертає: `login`, `pass`, `phone`.

- `root` може запитувати будь-якого користувача
- `user` може лише себе

### POST `/v1/api/users`
Тіло: `login`, `pass`, `phone` (обов’язкові).

- `root` створює будь-які записи
- `user` може створювати/оновлювати лише свій запис (за `login`)

Повертає: `id`, `login`, `pass`, `phone`.

### PUT `/v1/api/users`
Тіло: `id`, `phone`, `pass`, `login` (обов’язковий лише для `root`).

- `root` може оновлювати будь-якого користувача, включно з `login`
- `user` може оновлювати лише свої дані, змінювати `login` не можна

Повертає: `id`.

### DELETE `/v1/api/users`
Тіло: `id` (обов’язкове).

- лише `root`

Повертає: `204 No Content`.

## Формат помилок

Усі помилки у форматі:

```
{
  "status": <HTTP_CODE>,
  "message": "<текст>"
}
```

## Swagger

Документація:
- `/v1/api/users/doc`
- `/v1/api/users/doc/ui`

## Приклади запитів

### Отримати користувача (root)
```
curl -H "Authorization: Bearer root-token-123" \
  http://127.0.0.1:8000/v1/api/users/1
```

### Створити користувача (root)
```
curl -X POST http://127.0.0.1:8000/v1/api/users \
  -H "Authorization: Bearer root-token-123" \
  -H "Content-Type: application/json" \
  -d '{"login":"user2","pass":"pass2","phone":"12345678"}'
```

### Оновити користувача (root)
```
curl -X PUT http://127.0.0.1:8000/v1/api/users \
  -H "Authorization: Bearer root-token-123" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"login":"root","pass":"root","phone":"00000000"}'
```

### Оновити свої дані (user, без login)
```
curl -X PUT http://127.0.0.1:8000/v1/api/users \
  -H "Authorization: Bearer user-token-123" \
  -H "Content-Type: application/json" \
  -d '{"id":2,"pass":"user","phone":"11111111"}'
```

### Видалити користувача (root)
```
curl -X DELETE http://127.0.0.1:8000/v1/api/users \
  -H "Authorization: Bearer root-token-123" \
  -H "Content-Type: application/json" \
  -d '{"id":2}'
```
