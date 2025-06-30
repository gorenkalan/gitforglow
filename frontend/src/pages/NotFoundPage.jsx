import React from 'react';
import { Link } from 'react-router-dom';
import { Frown } from 'lucide-react';

const NotFoundPage = () => {
    return (
        <div className="flex flex-col items-center justify-center text-center px-6 py-20">
            <Frown className="text-yellow-500 mb-4" size={64} />
            <h1 className="text-4xl font-bold mb-2">404</h1>
            <h2 className="text-2xl font-semibold mb-4">Page Not Found</h2>
            <p className="text-gray-600 mb-6">The page you're looking for doesn't exist or has been moved.</p>
            <Link 
                to="/" 
                className="bg-primary text-white px-6 py-3 rounded-full font-semibold hover:bg-gray-800 transition-colors"
            >
                Go to Homepage
            </Link>
        </div>
    );
};

export default NotFoundPage;