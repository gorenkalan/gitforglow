import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { createOrder, verifyPayment } from '../services/api';
import { Plus, Minus, Trash2 } from 'lucide-react';
import Spinner from '../components/Spinner';

const CartPage = () => {
    const { cartItems, updateQuantity, removeFromCart, subtotal, shipping, tax, total, clearCart } = useCart();
    const navigate = useNavigate();
    
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', address: '' });
    const [formTimestamp, setFormTimestamp] = useState('');
    const [honeypotValue, setHoneypotValue] = useState('');

    useEffect(() => {
        setFormTimestamp(Date.now());
    }, []);

    const handleCheckout = async () => {
        if (!customerInfo.name || !customerInfo.phone || !customerInfo.address) {
            setError('Please fill in all shipping details.');
            return;
        }
        setError('');
        setLoading(true);

        try {
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
                field_9a3b: honeypotValue,
            };

            const { data: orderData } = await createOrder(orderPayload);
            
            const options = {
                key: orderData.razorpay_key_id,
                amount: orderData.amount,
                order_id: orderData.razorpay_order_id,
                handler: async function (response) {
                    setLoading(true);
                    const verificationData = { ...response, internal_order_id: orderData.internal_order_id };
                    const verifyRes = await verifyPayment(verificationData);
                    if (verifyRes.data.status === 'success') {
                        clearCart();
                        navigate('/order-success', { state: { orderId: verifyRes.data.orderId } });
                    } else {
                        setError('Payment verification failed. Please contact support.');
                    }
                    setLoading(false);
                },
                modal: { ondismiss: function() { setLoading(false); setError('Payment was cancelled.'); } },
                prefill: { name: customerInfo.name, phone: customerInfo.phone },
                theme: { color: "#FF69B4" }
            };
            
            const rzp = new window.Razorpay(options);
            rzp.on('payment.failed', function (response){
                setError(`Payment failed: ${response.error.description}`);
                setLoading(false);
            });
            rzp.open();

        } catch (err) {
            const errorMessage = err.response?.data?.error || 'Could not initiate checkout.';
            setError(errorMessage);
            setLoading(false);
        }
    };
    
    if (cartItems.length === 0) {
        return (
            <div className="text-center py-20 px-6">
                <h1 className="text-2xl font-bold mb-4">Your Cart is Empty</h1>
                <p className="text-gray-600">Please add some products to continue.</p>
            </div>
        );
    }

    return (
        <div className="px-6 py-6 max-w-4xl mx-auto">
            <h1 className="text-2xl font-bold mb-6">Your Cart & Checkout</h1>
            <div className="md:grid md:grid-cols-3 md:gap-8">
                <div className="md:col-span-2">
                    <div className="mb-6 space-y-4">
                        {cartItems.map((item) => (
                            <div key={item.variation.variationId} className="flex items-center space-x-4 border-b pb-4">
                                <img src={item.variation.imageUrl} alt={item.product.name} className="w-20 h-20 rounded-lg object-cover"/>
                                <div className="flex-grow">
                                    <h3 className="font-semibold">{item.product.name}</h3>
                                    <p className="text-sm text-gray-500">Color: {item.variation.colorName}</p>
                                    <p className="font-semibold text-accent">₹{item.product.basePrice.toFixed(2)}</p>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <button onClick={() => updateQuantity(item.variation.variationId, item.quantity - 1)} className="p-1 border rounded-full"><Minus size={14}/></button>
                                    <span className="w-8 text-center">{item.quantity}</span>
                                    <button onClick={() => updateQuantity(item.variation.variationId, Math.min(item.quantity + 1, item.variation.stock))} disabled={item.quantity >= item.variation.stock} className="p-1 border rounded-full disabled:opacity-50"><Plus size={14}/></button>
                                </div>
                                <button onClick={() => removeFromCart(item.variation.variationId)} className="text-gray-400 hover:text-red-500"><Trash2 size={18}/></button>
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
                        {error && <p className="text-red-500 text-center text-sm mb-4">{error}</p>}
                        <button onClick={handleCheckout} disabled={loading} className="w-full bg-primary text-white py-3 rounded-full font-semibold flex justify-center items-center disabled:bg-gray-400">
                            {loading ? <Spinner/> : `Pay with Razorpay`}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CartPage;