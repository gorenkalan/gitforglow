import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { createOrder, verifyPayment } from '../services/api';
import { Plus, Minus, Trash2 } from 'lucide-react';
import Spinner from '../components/Spinner';

const CartPage = () => {
    // ... (existing state hooks for cart, loading, customerInfo, etc. remain the same)
    const { cartItems, updateQuantity, removeFromCart, subtotal, clearCart } = useCart();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', address: '' });

    // --- NEW: Spam Protection State ---
    const [formTimestamp, setFormTimestamp] = useState('');
    const [honeypotValue, setHoneypotValue] = useState('');

    useEffect(() => {
        // Set the timestamp when the component mounts
        setFormTimestamp(Date.now());
    }, []);
    // --- End of New Code ---

    const shipping = subtotal > 0 ? 5.99 : 0;
    const total = subtotal + shipping;

    const handleCheckout = async () => {
        if (!customerInfo.name || !customerInfo.phone || !customerInfo.address) {
            setError('Please fill in all customer details.');
            return;
        }
        setError('');
        setLoading(true);

        try {
            const orderPayload = {
                customer_info: customerInfo,
                items: cartItems.map(item => ({ 
                    productId: item.product.id,
                    variationId: item.variation.variationId,
                    name: item.product.name,
                    quantity: item.quantity,
                    price: item.product.basePrice,
                })),
                total: total,
                // --- NEW: Send spam protection data to backend ---
                form_timestamp: formTimestamp,
                hp_email: honeypotValue, // Honeypot field
            };
            
            // The rest of the checkout logic remains the same
            const { data: orderData } = await createOrder(orderPayload);
            
            const options = {
                key: orderData.razorpay_key_id,
                amount: orderData.amount,
                order_id: orderData.razorpay_order_id,
                handler: async function (response) {
                    const verificationData = {
                        ...response,
                        internal_order_id: orderData.internal_order_id,
                    };
                    const verifyRes = await verifyPayment(verificationData);
                    if(verifyRes.data.status === 'success') {
                        clearCart();
                        navigate('/order-success', { state: { orderId: verifyRes.data.orderId } });
                    } else {
                        setError('Payment verification failed. Please contact support.');
                    }
                },
                prefill: { name: customerInfo.name, },
                theme: { color: "#FF69B4" }
            };
            
            const rzp = new window.Razorpay(options);
            rzp.on('payment.failed', function (response){ setError(`Payment failed: ${response.error.description}`); });
            rzp.open();

        } catch (err) {
            const errorMessage = err.response?.data?.error || 'Could not initiate checkout. Please try again.';
            setError(errorMessage);
        } finally {
            setLoading(false);
        }
    };
    
    // The JSX part
    return (
        <div className="px-6 py-6">
            {/* ... (Cart items display and summary remain the same) ... */}

            <div className="mb-6">
                <h2 className="text-xl font-bold mb-4">Customer Details</h2>
                <form id="checkout-form">
                    <div className="space-y-4">
                        <input type="text" placeholder="Full Name *" value={customerInfo.name} onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg"/>
                        <input type="tel" placeholder="Phone Number *" value={customerInfo.phone} onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg"/>
                        <textarea placeholder="Address *" value={customerInfo.address} onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})} className="w-full px-4 py-3 border border-gray-300 rounded-lg h-24"/>
                    </div>
                    
                    {/* --- NEW: Spam Protection Fields --- */}
                    {/* Honeypot field: visually hidden from users */}
                    <div style={{ position: 'absolute', left: '-9999px' }} aria-hidden="true">
                        <label htmlFor="hp_email">Please leave this field empty</label>
                        <input
                            id="hp_email"
                            type="email"
                            name="hp_email"
                            tabIndex="-1"
                            autoComplete="off"
                            value={honeypotValue}
                            onChange={(e) => setHoneypotValue(e.target.value)}
                        />
                    </div>
                    {/* Timestamp field */}
                    <input type="hidden" name="form_timestamp" value={formTimestamp} />
                    {/* --- End of New Code --- */}

                </form>
            </div>
            
            {error && <p className="text-red-500 text-center mb-4">{error}</p>}

            <button onClick={handleCheckout} disabled={loading} className="w-full bg-primary text-white py-4 rounded-full font-semibold flex justify-center items-center">
                {loading ? <Spinner/> : `Checkout - $${total.toFixed(2)}`}
            </button>
        </div>
    );
};

export default CartPage;