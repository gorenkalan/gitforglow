import React, { useState, useEffect } from 'react';
import { useCart } from '../contexts/CartContext';
import { useNavigate } from 'react-router-dom';
import { X, Plus, Minus, Trash2, ShoppingBag } from 'lucide-react';
import { createOrder, verifyPayment } from '../services/api';
import Spinner from './Spinner';

const CartModal = () => {
    const { 
        isCartOpen, closeCart, cartItems, cartCount, 
        updateQuantity, removeFromCart, clearCart,
        subtotal, shipping, tax, total 
    } = useCart();

    const navigate = useNavigate();
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [error, setError] = useState('');
    const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', address: '' });
    const [formTimestamp, setFormTimestamp] = useState('');
    const [honeypotValue, setHoneypotValue] = useState('');

    useEffect(() => {
        if (isCartOpen) {
            setFormTimestamp(Date.now());
        }
    }, [isCartOpen]);
    
    const handleCheckout = async () => {
        if (!customerInfo.name || !customerInfo.phone || !customerInfo.address) {
            setError('Please fill in all shipping details.');
            return;
        }
        setError('');
        setIsCheckingOut(true);

        try {
            // --- THE CRITICAL FIX ---
            const totalInPaise = Math.round(total * 100);

            const orderPayload = {
                customer_info: customerInfo,
                items: cartItems.map(item => ({ 
                    productId: item.product.id,
                    variationId: item.variation.variationId,
                    name: item.product.name,
                    quantity: item.quantity,
                    price: item.product.basePrice,
                })),
                total_in_paise: totalInPaise,
                total_display: total,
                form_timestamp: formTimestamp,
                field_9a3b: honeypotValue, // Use the non-autofill name
            };
            // --- END OF FIX ---

            const { data: orderData } = await createOrder(orderPayload);
            
            const options = {
                key: orderData.razorpay_key_id,
                amount: orderData.amount,
                order_id: orderData.razorpay_order_id,
                handler: async function (response) {
                    setIsCheckingOut(true);
                    const verificationData = { ...response, internal_order_id: orderData.internal_order_id };
                    const verifyRes = await verifyPayment(verificationData);
                    if(verifyRes.data.status === 'success') {
                        clearCart();
                        closeCart();
                        navigate('/order-success', { state: { orderId: verifyRes.data.orderId } });
                    } else {
                        setError('Payment verification failed. Please contact support.');
                    }
                    setIsCheckingOut(false);
                },
                modal: { ondismiss: function() { setIsCheckingOut(false); setError('Payment was cancelled.'); } },
                prefill: { name: customerInfo.name, phone: customerInfo.phone },
                theme: { color: "#FF69B4" }
            };
            
            const rzp = new window.Razorpay(options);
            rzp.on('payment.failed', function (response){
                setError(`Payment failed: ${response.error.description}`);
                setIsCheckingOut(false);
            });
            rzp.open();

        } catch (err) {
            const errorMessage = err.response?.data?.error || 'Could not initiate checkout.';
            setError(errorMessage);
            setIsCheckingOut(false);
        }
    };

    if (!isCartOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 z-50 flex justify-end" onClick={closeCart}>
            <div className="w-full max-w-md h-full bg-white shadow-2xl flex flex-col" onClick={(e) => e.stopPropagation()}>
                <div className="flex justify-between items-center p-4 border-b">
                    <h2 className="text-lg font-semibold">Your Cart ({cartCount})</h2>
                    <button onClick={closeCart} className="p-2 hover:bg-gray-100 rounded-full"><X size={24} /></button>
                </div>

                {cartItems.length > 0 ? (
                    <>
                        <div className="flex-grow overflow-y-auto p-4 space-y-4">
                            {cartItems.map(item => (
                                <div key={item.variation.variationId} className="flex items-start space-x-4">
                                    <img src={item.variation.imageUrl} alt={item.product.name} className="w-16 h-16 rounded-md object-cover"/>
                                    <div className="flex-grow">
                                        <p className="font-semibold">{item.product.name}</p>
                                        <p className="text-sm text-gray-500">{item.variation.colorName}</p>
                                        <div className="flex items-center mt-2">
                                            <button onClick={() => updateQuantity(item.variation.variationId, item.quantity - 1)} className="p-1 border rounded-full"><Minus size={14}/></button>
                                            <span className="w-8 text-center">{item.quantity}</span>
                                            <button onClick={() => updateQuantity(item.variation.variationId, Math.min(item.quantity + 1, item.variation.stock))} disabled={item.quantity >= item.variation.stock} className="p-1 border rounded-full disabled:opacity-50"><Plus size={14}/></button>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold">₹{(item.product.basePrice * item.quantity).toFixed(2)}</p>
                                        <button onClick={() => removeFromCart(item.variation.variationId)} className="text-gray-400 hover:text-red-500 mt-2"><Trash2 size={16}/></button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="p-4 border-t space-y-4">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between"><span>Subtotal</span><span>₹{subtotal.toFixed(2)}</span></div>
                                <div className="flex justify-between"><span>Shipping</span><span>₹{shipping.toFixed(2)}</span></div>
                                <div className="flex justify-between"><span>Tax (5%)</span><span>₹{tax.toFixed(2)}</span></div>
                                <div className="flex justify-between font-bold text-base border-t pt-2 mt-2"><span>Total</span><span>₹{total.toFixed(2)}</span></div>
                            </div>
                            <div className="space-y-3">
                                <h3 className="font-semibold">Shipping Details</h3>
                                <input type="text" placeholder="Full Name *" value={customerInfo.name} onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})} className="w-full px-4 py-2 border rounded-md"/>
                                <input type="tel" placeholder="Phone Number *" value={customerInfo.phone} onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})} className="w-full px-4 py-2 border rounded-md"/>
                                <textarea placeholder="Address *" value={customerInfo.address} onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})} className="w-full px-4 py-2 border rounded-md h-20"/>
                                <div className="absolute w-0 h-0 overflow-hidden"><input id="field_9a3b" name="field_9a3b" type="text" value={honeypotValue} onChange={(e) => setHoneypotValue(e.target.value)} autoComplete="off" tabIndex="-1" /></div>
                            </div>
                            {error && <p className="text-red-500 text-center text-sm">{error}</p>}
                            <button onClick={handleCheckout} disabled={isCheckingOut} className="w-full bg-primary text-white py-3 rounded-md font-semibold flex justify-center items-center disabled:bg-gray-400">
                                {isCheckingOut ? <Spinner/> : `Pay with Razorpay`}
                            </button>
                        </div>
                    </>
                ) : (
                    <div className="flex-grow flex flex-col items-center justify-center text-center p-8">
                        <ShoppingBag size={48} className="text-gray-300 mb-4"/>
                        <h3 className="text-lg font-semibold">Your cart is empty</h3>
                        <p className="text-gray-500">Add some products to get started!</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default CartModal;