import React from 'react';
import { Link } from 'react-router-dom';
import { X } from 'lucide-react';

const NavigationMenu = ({ isOpen, onClose }) => {
  if (!isOpen) return null;

  const menuItems = [
    { name: 'All Products', path: '/products' },
    { name: 'Cream', path: '/category/cream' },
    { name: 'Serum', path: '/category/serum' },
    { name: 'Makeup', path: '/category/makeup' },
    { name: 'Skincare', path: '/category/skincare' },
    { name: 'Contact Us', path: '/contact' },
    { name: 'Terms & Conditions', path: '/terms' }
  ];

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50" onClick={onClose}>
      <div 
        className="bg-white w-80 h-full shadow-lg transition-transform transform duration-300 ease-in-out" 
        onClick={(e) => e.stopPropagation()}
        style={{ transform: isOpen ? 'translateX(0)' : 'translateX(-100%)' }}
      >
        <div className="p-6 border-b flex justify-between items-center">
          <h2 className="text-xl font-bold">Menu</h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full">
            <X size={24} />
          </button>
        </div>
        <nav className="p-6">
          {menuItems.map((item) => (
            <Link
              key={item.name}
              to={item.path}
              onClick={onClose}
              className="block w-full text-left py-3 text-lg border-b border-gray-200 hover:text-accent transition-colors"
            >
              {item.name}
            </Link>
          ))}
        </nav>
      </div>
    </div>
  );
};

export default NavigationMenu;