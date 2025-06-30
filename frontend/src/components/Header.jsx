import React from 'react';
import { Link } from 'react-router-dom';
import { Menu, ShoppingCart } from 'lucide-react';

const Header = ({ onMenuClick, cartCount }) => {
  return (
    <header className="bg-primary text-white px-6 py-6 flex items-center justify-between sticky top-0 z-40">
      <button onClick={onMenuClick} className="text-white">
        <Menu size={24} />
      </button>
      <Link
        to='/'
        className="font-bold text-xl absolute left-1/2 transform -translate-x-1/2"
        style={{ fontFamily: 'serif' }}
      >
        GLOW
      </Link>
      <Link to='/cart' className="relative">
        <ShoppingCart size={24} />
        {cartCount > 0 && (
          <span className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
            {cartCount}
          </span>
        )}
      </Link>
    </header>
  );
};

export default Header;