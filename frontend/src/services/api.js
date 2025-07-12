import axios from 'axios';

// This URL is correct and points to your backend API directory.
const API_BASE_URL = 'http://localhost/glow_ecommerce/backend/api/';

const api = axios.create({
  baseURL: API_BASE_URL,
});

// This is required for CORS to work with PHP sessions.
api.defaults.withCredentials = true;

// ==========================================================
// === THIS IS THE FINAL, COMBINED, AND CORRECT LOGIC ===
// ==========================================================

/**
 * For GET requests:
 * We pass all parameters, including the static 'endpoint', inside a single
 * `params` object. Axios will build the correct query string from this.
 * This is the logic that fixes the "products not loading" issue.
 */
export const getProducts = (params) => {
  return api.get('index.php', {
    params: {
      endpoint: 'products',
      ...params,
    },
  });
};

export const getCategories = () => {
  return api.get('index.php', {
    params: {
      endpoint: 'categories',
    },
  });
};

/**
 * For POST requests:
 * We put the endpoint directly in the URL's query string and pass the data
 * as the second argument. This is the simple, direct method that we know works.
 */
export const createOrder = (orderData) => {
  return api.post('index.php?endpoint=create-order', orderData);
};

export const verifyPayment = (paymentData) => {
  return api.post('index.php?endpoint=verify-payment', paymentData);
};

export default api;