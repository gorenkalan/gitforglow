import React from 'react';
import { useLocation, Link } from 'react-router-dom';
import { CheckCircle } from 'lucide-react';

const OrderSuccessPage = () => {
    const location = useLocation();
    const orderId = location.state?.orderId;

    return (
        <div className="flex flex-col items-center justify-center text-center px-6 py-20">
            <CheckCircle className="text-green-500 mb-4" size={64} />
            <h1 className="text-2xl font-bold mb-2">Order Placed Successfully!</h1>
            <p className="text-gray-600 mb-4">Thank you for your purchase. A notification has been sent.</p>
            {orderId && (
                <p className="text-gray-800 mb-6">
                    Your Order ID is: <span className="font-semibold bg-gray-100 px-2 py-1 rounded">{orderId}</span>
                </p>
            )}
            <Link 
                to="/products" 
                className="bg-primary text-white px-6 py-3 rounded-full font-semibold hover:bg-gray-800 transition-colors"
            >
                Continue Shopping
            </Link>
        </div>
    );
};

export default OrderSuccessPage;