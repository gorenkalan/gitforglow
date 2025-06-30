import React from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { ShoppingBag } from 'lucide-react';

const ProductCard = ({ product }) => {
  const { addToCart } = useCart();

  // --- Defensive Programming: The Stability Check ---
  // This is a crucial check. If a product from the API is missing a name or a price,
  // we will not attempt to render it, which prevents the entire page from crashing.
  if (!product || !product.name || typeof product.basePrice === 'undefined') {
    // Return null to simply skip rendering this broken item.
    return null; 
  }

  // --- Variation Logic ---
  // Check if there are any variations available for this product.
  const hasVariations = Array.isArray(product.variations) && product.variations.length > 0;
  
  // Determine which variation to display. Use the first one if it exists,
  // otherwise, create a placeholder for products without inventory yet.
  const displayVariation = hasVariations 
    ? product.variations[0] 
    : { imageUrl: 'https://dummyimage.com/400x400/e0e0e0/b0b0b0.png&text=No+Image', variationId: null };

  // --- Add to Cart Handler ---
  const handleAddToCart = (e) => {
    e.preventDefault(); // This stops the Link from navigating when the button is clicked.
    
    // Only add to cart if there's a real variation to add.
    if (hasVariations) {
      addToCart(product, displayVariation, 1);
    } else {
      // If the "Buy" button were somehow enabled, this would prevent an error.
      alert('This product is currently unavailable.');
    }
  };

  return (
    // The entire card is a link to the product's detail page.
    <Link to={`/product/${product.id}`} className="group flex flex-col text-sm">
        <div className="bg-gray-100 rounded-lg overflow-hidden aspect-w-1 aspect-h-1">
            <img 
              src={displayVariation.imageUrl} 
              alt={product.name} 
              className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
        </div>
        <div className="pt-4 flex flex-col flex-grow">
            {/* Product Name and Price */}
            <div className="flex justify-between items-start mb-2 flex-grow">
                <h3 className="font-semibold text-primary pr-2">{product.name}</h3>
                {/* This now correctly uses 'basePrice' as defined in your Google Sheet */}
                <span className="text-accent font-bold">${product.basePrice.toFixed(2)}</span>
            </div>

            {/* Color Swatches and Buy Button */}
            <div className="flex items-center justify-between text-gray-500 mt-auto">
                {/* Color swatches section */}
                <div className="flex items-center space-x-1 h-4">
                    {/* Only show swatches if variations exist */}
                    {hasVariations && product.variations.slice(0, 3).map((variation) => (
                        <div
                            key={variation.variationId}
                            className="w-4 h-4 rounded-full border"
                            style={{ backgroundColor: variation.colorHex }}
                            title={variation.colorName} // Add a tooltip for the color name
                        />
                    ))}
                </div>

                {/* The "Buy" button */}
                <button
                    onClick={handleAddToCart}
                    // The button is disabled if there are no variations, preventing errors.
                    disabled={!hasVariations}
                    className="bg-accent text-white px-3 py-1 rounded-full font-semibold hover:bg-pink-500 transition-colors flex items-center gap-1 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                    <ShoppingBag size={14} />
                    Buy
                </button>
            </div>
        </div>
    </Link>
  );
};

export default ProductCard;