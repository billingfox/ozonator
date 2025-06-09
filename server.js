const express = require('express');
const path = require('path');
const OzonApi = require('./ozonApi');
const Database = require('./db');

const app = express();
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(express.static(path.join(__dirname, 'public')));

const api = new OzonApi();
const db = new Database();

app.use(express.urlencoded({ extended: true }));

app.get('/', async (req, res) => {
  const products = await db.getAllProducts();
  const last = await db.getLastUpdateInfo();
  res.render('index', { products, last });
});

app.post('/update', async (req, res) => {
  try {
    const list = await api.getProducts();
    const productIds = list.result.items.map(i => i.product_id);
    const info = await api.getProductInfo(productIds);
    await db.saveUpdateInfo(info.items.length);
    await db.saveProducts(info.items);
    res.redirect('/');
  } catch (e) {
    res.status(500).send(e.message);
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log('Server running on', PORT));
