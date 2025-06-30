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
                    setSelectedVariation(foundProduct.variations[0]);
                }
            } catch (error) {
                console.error("Failed to fetch product details:", error);
            } finally {
                setLoading(false);
            }
        };
        fetchProduct();
    }, [productId]);

    const handleAddToCart = () => {
        if (product && selectedVariation) {
            addToCart(product, selectedVariation, quantity);
        }
    };

    if (loading) return <div className="flex justify-center items-center h-screen"><Spinner /></div>;
    if (!product || !selectedVariation) return <div className="text-center py-20">Product not found or out of stock.</div>;

    return (
        <div className="px-6 py-6">
            <div className="mb-6 bg-gray-100 rounded-3xl overflow-hidden aspect-square">
                <img src={selectedVariation.imageUrl} alt={product.name} className="w-full h-full object-cover"/>
            </div>

            <div className="space-y-4">
                <h1 className="text-2xl font-bold">{product.name}</h1>
                <div className="flex items-center justify-between">
                    <p className="text-2xl font-bold text-accent">${product.basePrice.toFixed(2)}</p>
                     <div className="flex items-center gap-1">
                        <Star className="text-yellow-400 fill-yellow-400" size={20}/>
                        <span className="font-semibold">{product.rating}</span>
                        <span className="text-gray-500">({product.reviews} reviews)</span>
                    </div>
                </div>

                {product.variations.length > 1 && (
                    <div>
                        <h3 className="font-semibold mb-2">Color: <span className="font-normal">{selectedVariation.colorName}</span></h3>
                        <div className="flex space-x-3">
                            {product.variations.map((variation) => (
                                <button
                                    key={variation.variationId}
                                    onClick={() => setSelectedVariation(variation)}
                                    className={`w-8 h-8 rounded-full color-swatch ${selectedVariation.variationId === variation.variationId ? 'selected' : ''}`}
                                    style={{ backgroundColor: variation.colorHex }}
                                />
                            ))}
                        </div>
                    </div>
                )}
                
                {/* Quantity, Description (remain the same) */}

                <button onClick={handleAddToCart} className="w-full bg-primary text-white py-4 rounded-full font-semibold">
                    Add to Cart - ${(product.basePrice * quantity).toFixed(2)}
                </button>
            </div>
        </div>
    );
};

export default ProductDetailsPage;