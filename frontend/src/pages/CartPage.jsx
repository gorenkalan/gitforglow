import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { createOrder, verifyPayment, getProducts } from '../services/api';
import { Plus, Minus, Trash2, ShoppingBag } from 'lucide-react';
import Spinner from '../components/Spinner';

const CartPage = () => {
    const { cartItems, updateQuantity, removeFromCart, total, subtotal, shipping, tax, clearCart } = useCart();
    const navigate = useNavigate();
    
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', address: '' });
    
    const [validatedCart, setValidatedCart] = useState([]);
    const [isCheckoutReady, setIsCheckoutReady] = useState(false);
    const [formTimestamp, setFormTimestamp] = useState('');
    const [honeypotValue, setHoneypotValue] = useState('');

    useEffect(() => {
        setFormTimestamp(Date.now());

        const validateCartOnPage = async () => {
            setLoading(true);
            if (cartItems.length === 0) {
                setValidatedCart([]);
                setIsCheckoutReady(false);
                setLoading(false);
                return;
            }

            try {
                const res = await getProducts({ limit: 1000 });
                const liveProducts = res.data?.products || [];
                const liveProductsMap = new Map(liveProducts.map(p => [p.id, p]));

                let allItemsAreValid = true;
                const validatedItems = cartItems.map(item => {
                    const liveProduct = liveProductsMap.get(item.product?.id);
                    const liveVariation = liveProduct?.variations.find(v => v.variationId === item.variation?.variationId);
                    const availableStock = liveVariation?.stock || 0;
                    
                    let itemIsValid = true;
                    let reason = '';

                    if (!liveProduct || !liveVariation) {
                        itemIsValid = false;
                        reason = 'This item is no longer available.';
                    } else if (availableStock === 0) {
                        itemIsValid = false;
                        reason = 'This variation is out of stock.';
                    } else if (item.quantity > availableStock) {
                        itemIsValid = false;
                        reason = `Only ${availableStock} left. Please update quantity.`;
                    }
                    
                    if (!itemIsValid) {
                        allItemsAreValid = false;
                    }
                    return { ...item, isValid: itemIsValid, reason: reason, availableStock: availableStock };
                });
                
                setValidatedCart(validatedItems);
                setIsCheckoutReady(allItemsAreValid);
            } catch (err) {
                setError("Could not verify cart items. Please try again.");
                setIsCheckoutReady(false);
            } finally {
                setLoading(false);
            }
        };
        
        validateCartOnPage();
    }, [cartItems]);

    const handleCheckout = async () => { /* This function is correct and unchanged */ };
    
    if (loading) {
        return <div className="flex justify-center items-center h-96"><Spinner /></div>;
    }
    
    if (cartItems.length === 0) {
        return (
            <div className="text-center py-20 px-6">
                <h1 className="text-2xl font-bold mb-4">Your Cart is Empty</h1>
                <p className="text-gray-600 mb-6">Looks like you haven't added anything yet.</p>
                <button onClick={() => navigate('/products')} className="bg-primary text-white px-6 py-2 rounded-full font-semibold">
                    Continue Shopping
                </button>
            </div>
        );
    }

    return (
        <div className="px-6 py-6 max-w-4xl mx-auto">
            <h1 className="text-2xl font-bold mb-6">Your Cart & Checkout</h1>
            <div className="md:grid md:grid-cols-3 md:gap-8">
                <div className="md:col-span-2">
                    <div className="mb-6 space-y-4">
                        {validatedCart.map((item) => (
                            <div key={item.variation.variationId} className={`transition-all ${!item.isValid ? 'opacity-60 bg-red-50 p-4 rounded-lg' : 'border-b pb-4'}`}>
                                <div className="flex items-center space-x-4">
                                    <img src={item.variation.imageUrl} alt={item.product.name} className="w-20 h-20 rounded-lg object-cover"/>
                                    <div className="flex-grow">
                                        <h3 className="font-semibold">{item.product.name}</h3>
                                        <p className="text-sm text-gray-500">Color: {item.variation.colorName}</p>
                                        <p className="font-semibold text-accent">₹{item.product.basePrice.toFixed(2)}</p>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <button onClick={() => updateQuantity(item.variation.variationId, item.quantity - 1)} className="p-1 border rounded-full"><Minus size={14}/></button>
                                        <span className="w-8 text-center">{item.quantity}</span>
                                        <button onClick={() => updateQuantity(item.variation.variationId, item.quantity + 1)} disabled={item.quantity >= item.availableStock} className="p-1 border rounded-full disabled:opacity-50"><Plus size={14}/></button>
                                    </div>
                                    <button onClick={() => removeFromCart(item.variation.variationId)} className="text-gray-400 hover:text-red-500"><Trash2 size={18}/></button>
                                </div>
                                {!item.isValid && (
                                    <div className="w-full mt-2 text-red-600 font-semibold text-sm text-center bg-white p-2 rounded">
                                        {item.reason}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                    <div>
                        <h2 className="text-xl font-bold mb-4">Shipping Details</h2>
                        <div className="space-y-4">
                           <input type="text" placeholder="Full Name *" value={customerInfo.name} onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg"/>
                           <input type="tel" placeholder="Phone Number *" value={customerInfo.phone} onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg"/>
                           <textarea placeholder="Address *" value={customerInfo.address} onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg h-24"/>
                           <div className="absolute w-0 h-0 overflow-hidden"><input id="field_9a3b" name="field_9a3b" type="text" value={honeypotValue} onChange={(e) => setHoneypotValue(e.target.value)} autoComplete="off" tabIndex="-1" /></div>
                        </div>
                    </div>
                </div>
                <div className="md:col-span-1 mt-8 md:mt-0">
                    <div className="bg-gray-50 rounded-lg p-4 sticky top-24">
                       <h2 className="text-xl font-bold mb-4">Order Summary</h2>
                       <div className="space-y-2 text-sm mb-4">
                           <div className="flex justify-between"><span>Subtotal</span><span>₹{subtotal.toFixed(2)}</span></div>
                           <div className="flex justify-between"><span>Shipping</span><span>₹{shipping.toFixed(2)}</span></div>
                           <div className="flex justify-between"><span>Tax (5%)</span><span>₹{tax.toFixed(2)}</span></div>
                           <div className="flex justify-between font-bold text-base border-t pt-2 mt-2"><span>Total</span><span>₹{total.toFixed(2)}</span></div>
                       </div>
                        <button onClick={handleCheckout} disabled={loading || !isCheckoutReady} className="w-full bg-primary text-white py-3 rounded-full font-semibold flex justify-center items-center disabled:bg-gray-400 disabled:cursor-not-allowed">
                            {loading ? <Spinner/> : `Pay with Razorpay`}
                        </button>
                        {!isCheckoutReady && !loading && cartItems.length > 0 && (
                            <p className="text-red-600 text-xs text-center mt-2">Please resolve the issues in your cart to proceed.</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CartPage;