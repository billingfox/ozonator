# Ozon API Tools

Набор инструментов для работы с API Ozon и хранения информации в SQLite.
В каталоге теперь находится версия на Node.js, обеспечивающая схожий функционал.

## Установка

1. Требуется Node.js 18+ и npm. Для старой версии PHP скрипты также сохранены.
2. Создайте файл `.env` в корне проекта и укажите в нём:
   ```
   OZON_CLIENT_ID=your_client_id
   OZON_API_KEY=your_api_key
   ```
3. Установите зависимости командой `npm install`.
4. Запустите сервер `node server.js` и откройте `http://localhost:3000`.
5. Старые PHP‑скрипты можно запускать через веб‑сервер или `php -S` при необходимости.

## Состав

- `server.js` – основной сервер Express.
- `index.php` и другие PHP‑скрипты сохранены для совместимости.

База данных находится в каталоге `/var/www/html/db/` и создаётся автоматически.
