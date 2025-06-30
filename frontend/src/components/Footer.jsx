import React from 'react';
import { Link } from 'react-router-dom';
import { Instagram, Facebook, Twitter, ArrowUp } from 'lucide-react';

const Footer = () => {
  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <footer className="bg-primary text-white mx-4 md:mx-6 rounded-full mt-8 mb-4 sticky bottom-4 z-10">
      <div className="px-6 md:px-10 py-4 flex justify-between items-center">
        <div className="hidden md:flex space-x-6">
          <Link to='/' className="hover:text-accent">Home</Link>
          <a href="#" className="hover:text-accent">Help</a>
        </div>
        <div className="flex space-x-4">
          <a href="#" className="hover:text-accent"><Instagram size={20} /></a>
          <a href="#" className="hover:text-accent"><Facebook size={20} /></a>
          <a href="#" className="hover:text-accent"><Twitter size={20} /></a>
        </div>
        <button onClick={scrollToTop} className="hover:text-accent">
          <ArrowUp size={20} />
        </button>
      </div>
    </footer>
  );
};

export default Footer;