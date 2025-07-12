import React, { useState } from 'react';
import { Routes, Route } from 'react-router-dom';

// Core Layout Components
import Header from './components/Header';
import Footer from './components/Footer';
import NavigationMenu from './components/NavigationMenu';
import ScrollToTop from './components/ScrollToTop';
import CartModal from './components/CartModal'; // The slide-out cart UI

// Page Components
import HomePage from './pages/HomePage';
import ProductsPage from './pages/ProductsPage';
import ProductDetailsPage from './pages/ProductDetailsPage';
import CartPage from './pages/CartPage'; // The dedicated checkout page
import OrderSuccessPage from './pages/OrderSuccessPage';
import NotFoundPage from './pages/NotFoundPage';
import CategoriesPage from './pages/CategoriesPage';

// App-wide Styles
import './App.css';

/**
 * The main component for the entire application.
 * Its primary jobs are to manage the main layout, control the visibility
 * of the navigation menu, and define all the URL routes.
 */
function App() {
  // This state is only for the main hamburger navigation menu. It is
  // correctly placed here because the menu is part of the main app layout.
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  // Functions to control the main navigation menu's visibility.
  const handleMenuToggle = () => setIsMenuOpen(!isMenuOpen);
  const closeMenu = () => setIsMenuOpen(false);

  return (
    <>
      {/* This component ensures navigation to a new page scrolls the user to the top. */}
      <ScrollToTop />
      
      {/* 
        The CartModal is rendered here at the top level. It is hidden by default 
        and will become visible when its internal state is changed via the CartContext.
        This allows it to appear as an overlay on top of any other page.
      */}
      <CartModal /> 
      
      <div className="App flex flex-col min-h-screen bg-white">
        
        {/* 
          The Header receives the function to toggle the main menu.
          It gets all of its cart-related functionality (like the cart count and
          the function to open the modal) directly from the CartContext via the useCart hook.
        */}
        <Header onMenuClick={handleMenuToggle} />
        
        <NavigationMenu isOpen={isMenuOpen} onClose={closeMenu} />
        
        <main className="flex-grow">
          {/* This is the primary job of App.jsx: defining the routes for the application. */}
          <Routes>
            <Route path="/" element={<HomePage />} />
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