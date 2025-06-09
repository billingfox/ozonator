const axios = require('axios');
const fs = require('fs');
require('dotenv').config();

class OzonApi {
  constructor() {
    this.clientId = process.env.OZON_CLIENT_ID || '';
    this.apiKey = process.env.OZON_API_KEY || '';
    this.apiUrl = 'https://api-seller.ozon.ru';
    if (!this.clientId || !this.apiKey) {
      throw new Error('OZON API credentials not found in .env file');
    }
  }

  async request(endpoint, data = {}) {
    const url = `${this.apiUrl}${endpoint}`;
    const headers = {
      'Client-Id': this.clientId,
      'Api-Key': this.apiKey,
      'Content-Type': 'application/json'
    };
    const res = await axios.post(url, data, { headers });
    return res.data;
  }

  getProducts(filter = {}, lastId = '', limit = 100) {
    const data = { filter: { visibility: 'ALL', ...filter }, last_id: lastId, limit };
    return this.request('/v3/product/list', data);
  }

  getProductInfo(productIds = []) {
    if (!productIds.length) throw new Error('productIds required');
    const data = { product_id: productIds };
    return this.request('/v3/product/info/list', data);
  }

  getWarehouseStocks() {
    const data = { filter: { stock_types: ['STOCK_TYPE_VALID'] }, limit: 1000, offset: 0 };
    return this.request('/v1/analytics/manage/stocks', data);
  }

  getStockOnWarehouses(limit = 1000, offset = 0, warehouseType = 'ALL') {
    const data = { limit, offset, warehouse_type: warehouseType };
    return this.request('/v2/analytics/stock_on_warehouses', data).then(r => r.result.rows);
  }
}

module.exports = OzonApi;
