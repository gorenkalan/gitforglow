import React from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { ShoppingBag } from 'lucide-react';

const ProductCard = ({ product }) => {
  const { addToCart } = useCart();

  if (!product || !product.name || typeof product.basePrice === 'undefined') {
    return null; 
  }

  const hasVariations = Array.isArray(product.variations) && product.variations.length > 0;
  const firstAvailableVariation = hasVariations ? product.variations.find(v => (v.stock || 0) > 0) : null;
  const isSoldOut = !firstAvailableVariation;
  const displayVariation = firstAvailableVariation || (hasVariations ? product.variations[0] : null) || { imageUrl: 'https://dummyimage.com/400x400/e0e0e0/b0b0b0.png&text=No+Image' };

  const handleAddToCart = (e) => {
    e.preventDefault();
    if (firstAvailableVariation) {
      addToCart(product, firstAvailableVariation, 1);
    }
  };

  return (
    <Link to={`/product/${product.id}`} className="group flex flex-col text-sm">
        <div className="relative bg-gray-100 rounded-lg overflow-hidden aspect-w-1 aspect-h-1">
            <img 
              src={displayVariation.imageUrl} 
              alt={product.name} 
              className={`w-full h-full object-cover transition-all duration-300 group-hover:scale-105 ${isSoldOut ? 'filter grayscale' : ''}`}
            />
            {isSoldOut && (
                <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <span className="text-white font-bold text-lg tracking-wider uppercase drop-shadow-md">Sold Out</span>
                </div>
            )}
        </div>
        <div className="pt-4 flex flex-col flex-grow">
            <div className="flex justify-between items-start mb-2 flex-grow">
                <h3 className="font-semibold text-primary pr-2">{product.name}</h3>
                <span className="text-accent font-bold">₹{product.basePrice.toFixed(2)}</span>
            </div>
            <div className="flex items-center justify-between text-gray-500 mt-auto">
                <div className="flex items-center space-x-1 h-4">
                    {hasVariations && product.variations.slice(0, 3).map((variation) => (
                        <div key={variation.variationId} className={`w-4 h-4 rounded-full border relative ${variation.stock === 0 ? 'opacity-40' : ''}`} style={{ backgroundColor: variation.colorHex }} title={`${variation.colorName}${variation.stock === 0 ? ' (Sold Out)' : ''}`} />
                    ))}
                </div>
                <button
                    onClick={handleAddToCart}
                    disabled={isSoldOut}
                    className="bg-accent text-white px-3 py-1 rounded-full font-semibold hover:bg-pink-500 transition-colors flex items-center gap-1 disabled:bg-gray-400 disabled:text-gray-600 disabled:cursor-not-allowed"
                >
                    <ShoppingBag size={14} />
                    {isSoldOut ? 'Unavailable' : 'Buy'}
                </button>
            </div>
        </div>
    </Link>
  );
};

export default ProductCard;