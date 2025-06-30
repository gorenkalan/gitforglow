import axios from 'axios';

// This URL is correct and stays the same.
const API_BASE_URL = 'http://localhost/glow_ecommerce/backend/api/';

const api = axios.create({
  baseURL: API_BASE_URL,
});

api.defaults.withCredentials = true;

// ==========================================================
// === THE FINAL FIX IS HERE ===
// We let Axios build the entire query string from a single params object.
// This is the most reliable way.
// ==========================================================

export const getProducts = (params) => {
  // We call index.php with NO query string here.
  // Instead, we add 'endpoint' to the params object that Axios will build.
  return api.get('index.php', {
    params: {
      endpoint: 'products', // Add the static endpoint parameter
      ...params,           // Merge any other dynamic params (like category, sort_by, etc.)
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

// The POST requests were already correct, but we'll re-verify them.
// The endpoint is in the query string, and the data is the second argument.
export const createOrder = (orderData) => api.post('index.php?endpoint=create-order', orderData);
export const verifyPayment = (paymentData) => api.post('index.php?endpoint=verify-payment', paymentData);

export default api;