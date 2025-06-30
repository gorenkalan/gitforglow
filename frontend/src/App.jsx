import React, { useState } from 'react';
import { Routes, Route } from 'react-router-dom';
import { useCart } from './contexts/CartContext';

import Header from './components/Header';
import Footer from './components/Footer';
import NavigationMenu from './components/NavigationMenu';
import ScrollToTop from './components/ScrollToTop';

import HomePage from './pages/HomePage';
import ProductsPage from './pages/ProductsPage';
import ProductDetailsPage from './pages/ProductDetailsPage';
import CartPage from './pages/CartPage';
import OrderSuccessPage from './pages/OrderSuccessPage';
import NotFoundPage from './pages/NotFoundPage';
// --- NEW: Import the new page ---
import CategoriesPage from './pages/CategoriesPage';

import './App.css';

function App() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const { cartCount } = useCart();

  const handleMenuToggle = () => setIsMenuOpen(!isMenuOpen);
  const closeMenu = () => setIsMenuOpen(false);

  return (
    <>
      <ScrollToTop />
      <div className="App flex flex-col min-h-screen bg-white">
        <Header onMenuClick={handleMenuToggle} cartCount={cartCount} />
        <NavigationMenu isOpen={isMenuOpen} onClose={closeMenu} />
        
        <main className="flex-grow">
          <Routes>
            <Route path="/" element={<HomePage />} />
            {/* --- NEW: Add the route for the categories page --- */}
            <Route path="/categories" element={<CategoriesPage />} />
            <Route path="/products" element={<ProductsPage />} />
            <Route path="/category/:categoryName" element={<ProductsPage />} />
            <Route path="/product/:productId" element={<ProductDetailsPage />} />
            <Route path="/cart" element={<CartPage />} />
            <Route path="/order-success" element={<OrderSuccessPage />} />
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </main>
        
        <Footer />
      </div>
    </>
  );
}

export default App;