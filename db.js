const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const fs = require('fs');

class Database {
  constructor() {
    const dbDir = '/var/www/html/db';
    if (!fs.existsSync(dbDir)) fs.mkdirSync(dbDir, { recursive: true });
    this.db = new sqlite3.Database(path.join(dbDir, 'ozon_products.db'));
    this.init();
  }

  init() {
    const queries = [
      `CREATE TABLE IF NOT EXISTS products(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER UNIQUE,
        offer_id TEXT UNIQUE,
        sku INTEGER,
        name TEXT,
        price REAL,
        marketing_price REAL,
        currency_code TEXT,
        status TEXT,
        status_name TEXT,
        primary_image TEXT,
        images TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )`,
      `CREATE TABLE IF NOT EXISTS warehouses(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )`,
      `CREATE TABLE IF NOT EXISTS update_info(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        last_update DATETIME,
        total_products INTEGER
      )`
    ];
    this.db.serialize(() => {
      queries.forEach(q => this.db.run(q));
    });
  }

  saveUpdateInfo(count) {
    return new Promise((resolve, reject) => {
      const now = new Date().toISOString();
      this.db.run('DELETE FROM update_info', err => {
        if (err) return reject(err);
        this.db.run('INSERT INTO update_info(last_update,total_products) VALUES(?,?)', [now, count], err2 => {
          if (err2) reject(err2); else resolve();
        });
      });
    });
  }

  getLastUpdateInfo() {
    return new Promise((resolve, reject) => {
      this.db.get('SELECT * FROM update_info ORDER BY id DESC LIMIT 1', (err, row) => {
        if (err) reject(err); else resolve(row);
      });
    });
  }

  saveProducts(items) {
    const stmt = this.db.prepare('INSERT OR REPLACE INTO products(product_id,offer_id,sku,name,price,marketing_price,currency_code,status,status_name,primary_image,images) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    this.db.serialize(() => {
      items.forEach(p => {
        stmt.run([
          p.id,
          p.offer_id,
          p.sku,
          p.name,
          p.price,
          p.old_price,
          p.currency_code,
          p.status,
          p.status_name,
          (p.primary_image || (p.images ? p.images[0] : '')),
          JSON.stringify(p.images || [])
        ]);
      });
      stmt.finalize();
    });
  }

  getAllProducts() {
    return new Promise((resolve, reject) => {
      this.db.all('SELECT * FROM products ORDER BY id DESC', (err, rows) => {
        if (err) return reject(err);
        resolve(rows);
      });
    });
  }
}

module.exports = Database;
