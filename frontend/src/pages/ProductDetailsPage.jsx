import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { getProducts } from '../services/api';
import { useCart } from '../contexts/CartContext';
import { ChevronRight, Plus, Minus, Star } from 'lucide-react';
import Spinner from '../components/Spinner';

const ProductDetailsPage = () => {
    const { productId } = useParams();
    const { addToCart } = useCart();
    
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [selectedVariation, setSelectedVariation] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [showDescription, setShowDescription] = useState(true);

    useEffect(() => {
        const fetchProduct = async () => {
            setLoading(true);
            try {
                const res = await getProducts({ limit: 1000 });
                const foundProduct = res.data.products.find(p => p.id === productId);
                setProduct(foundProduct);
                if (foundProduct && foundProduct.variations.length > 0) {
                    const firstAvailable = foundProduct.variations.find(v => (v.stock || 0) > 0) || foundProduct.variations[0];
                    setSelectedVariation(firstAvailable);
                }
            } catch (error) { console.error("Failed to fetch product details:", error); } 
            finally { setLoading(false); }
        };
        fetchProduct();
    }, [productId]);
    
    const isCurrentVariationSoldOut = !selectedVariation || (selectedVariation.stock || 0) === 0;
    const maxQuantity = selectedVariation ? (selectedVariation.stock || 0) : 0;

    const handleAddToCart = () => {
        if (product && selectedVariation && !isCurrentVariationSoldOut) {
            const quantityToAdd = Math.min(quantity, maxQuantity);
            if (quantityToAdd > 0) {
                addToCart(product, selectedVariation, quantityToAdd);
            }
        }
    };
    
    const handleVariationChange = (variation) => {
        setSelectedVariation(variation);
        setQuantity(1);
    };

    if (loading) return <div className="flex justify-center items-center h-screen"><Spinner /></div>;
    if (!product) return <div className="text-center py-20"><h2>404</h2><p>Product not found.</p></div>;

    return (
        <div className="px-6 py-6 max-w-2xl mx-auto">
            <div className="mb-6 bg-gray-100 rounded-3xl overflow-hidden aspect-square">
                <img 
                    src={selectedVariation?.imageUrl || 'https://dummyimage.com/400x400/e0e0e0/b0b0b0.png&text=No+Image'} 
                    alt={product.name} 
                    className="w-full h-full object-cover"
                />
            </div>

            <div className="space-y-6">
                <h1 className="text-3xl font-bold">{product.name}</h1>
                <div className="flex items-center justify-between">
                    <p className="text-3xl font-bold text-accent">₹{product.basePrice.toFixed(2)}</p>
                </div>

                {product.variations && product.variations.length > 0 && (
                    <div>
                        <h3 className="font-semibold mb-3">Color: <span className="font-normal">{selectedVariation?.colorName}</span></h3>
                        <div className="flex flex-wrap gap-3">
                            {product.variations.map((variation) => {
                                const isSoldOut = (variation.stock || 0) === 0;
                                return (
                                    <button
                                        key={variation.variationId}
                                        onClick={() => handleVariationChange(variation)}
                                        className={`w-10 h-10 rounded-full border-2 transition-all duration-200 relative overflow-hidden ${selectedVariation?.variationId === variation.variationId ? 'border-accent scale-110 shadow-lg' : 'border-gray-300'}`}
                                        style={{ backgroundColor: variation.colorHex }}
                                        title={`${variation.colorName} ${isSoldOut ? '(Sold Out)' : ''}`}
                                    >
                                        {isSoldOut && (
                                            <div className="absolute inset-0 w-full h-full">
                                                <div style={{width: '150%', height: '1.5px'}} className="bg-red-500 bg-opacity-70 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 rotate-45"></div>
                                            </div>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
                
                <div className="flex items-center justify-between">
                    <h3 className="font-semibold">Quantity</h3>
                    <div className="flex items-center space-x-4">
                        <button onClick={() => setQuantity(prev => Math.max(1, prev - 1))} className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300"><Minus size={16}/></button>
                        <span className="text-lg font-semibold min-w-[2rem] text-center">{quantity}</span>
                        <button onClick={() => setQuantity(prev => Math.min(prev + 1, maxQuantity))} disabled={quantity >= maxQuantity || isCurrentVariationSoldOut} className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center hover:bg-gray-300 disabled:bg-gray-100 disabled:cursor-not-allowed"><Plus size={16}/></button>
                    </div>
                </div>
                {quantity >= maxQuantity && maxQuantity > 0 && !isCurrentVariationSoldOut && (
                    <p className="text-right text-accent text-sm -mt-4">Maximum stock for this variation reached.</p>
                )}

                <button 
                    onClick={handleAddToCart}
                    disabled={isCurrentVariationSoldOut}
                    className="w-full bg-primary text-white py-4 rounded-full font-semibold hover:bg-gray-800 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed text-lg"
                >
                    {isCurrentVariationSoldOut ? 'Sold Out' : `Add to Cart`}
                </button>
            </div>
        </div>
    );
};

export default ProductDetailsPage;