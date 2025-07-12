import React from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { Menu, ShoppingCart } from 'lucide-react';

const Header = ({ onMenuClick }) => {
  const { cartCount, toggleCart } = useCart();

  return (
    <header className="bg-primary text-white px-4 md:px-6 py-4 flex items-center justify-between sticky top-0 z-40">
      <div className="flex items-center gap-4">
        <button onClick={onMenuClick} className="text-white">
          <Menu size={24} />
        </button>
        <Link to='/' className="font-bold text-xl md:absolute md:left-1/2 md:-translate-x-1/2" style={{ fontFamily: 'serif' }}>
          GLOW
        </Link>
      </div>
      <button onClick={toggleCart} className="relative text-white">
        <ShoppingCart size={24} />
        {cartCount > 0 && (
          <span className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
            {cartCount}
          </span>
        )}
      </button>
    </header>
  );
};

export default Header;