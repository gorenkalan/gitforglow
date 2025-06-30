import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getCategories } from '../services/api';
import Spinner from '../components/Spinner';

const CategoriesPage = () => {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);

    // This is the same image mapping from the homepage
    const categoryImages = {
        creams: 'https://images.pexels.com/photos/3993398/pexels-photo-3993398.jpeg',
        serums: 'https://images.unsplash.com/photo-1598528738936-c50861cc75a9',
        cleansers: 'https://images.unsplash.com/photo-1573461160327-b450ce3d8e7f',
        masks: 'https://images.pexels.com/photos/3018845/pexels-photo-3018845.jpeg',
        sunscreens: 'https://images.unsplash.com/photo-1612817288484-6f916006741a',
        'body lotions': 'https://images.unsplash.com/photo-1585945037805-5fd82c2e60b1',
        haircare: 'https://images.pexels.com/photos/4672476/pexels-photo-4672476.jpeg',
        makeup: 'https://images.pexels.com/photos/1377034/pexels-photo-1377034.jpeg',
        fragrances: 'https://images.pexels.com/photos/3762879/pexels-photo-3762879.jpeg',
        'tools & accessories': 'https://dummyimage.com/250/cccccc/969696&text=Tools',
        'oral care': 'https://dummyimage.com/250/cccccc/969696&text=Oral+Care',
        'bath & shower': 'https://dummyimage.com/250/cccccc/969696&text=Bath',
        default: 'https://dummyimage.com/250/cccccc/969696&text=Category',
    };

    useEffect(() => {
        const fetchCategories = async () => {
            setLoading(true);
            try {
                const res = await getCategories();
                if (res.data && Array.isArray(res.data.categories)) {
                    setCategories(res.data.categories);
                }
            } catch (error) {
                console.error("Failed to fetch categories:", error);
            } finally {
                setLoading(false);
            }
        };
        fetchCategories();
    }, []);

    if (loading) {
        return <div className="flex justify-center items-center h-96"><Spinner /></div>;
    }

    return (
        <div className="px-6 py-6">
            <h1 className="text-2xl font-bold mb-6">Shop by Category</h1>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {categories.map((category) => {
                    const imageKey = category.toLowerCase();
                    const categoryLink = encodeURIComponent(imageKey);
                    return (
                        <Link
                            key={category}
                            to={`/category/${categoryLink}`}
                            className="group text-center"
                        >
                            <div className="bg-gray-100 rounded-lg overflow-hidden aspect-square mb-2 transition-transform duration-300 group-hover:scale-105">
                                <img
                                    src={categoryImages[imageKey] || categoryImages.default}
                                    alt={category}
                                    className="w-full h-full object-cover"
                                />
                            </div>
                            <h2 className="font-semibold text-primary">{category}</h2>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
};

export default CategoriesPage;