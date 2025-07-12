import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getProducts, getCategories } from '../services/api';
import { ChevronRight } from 'lucide-react';
import ProductCard from '../components/ProductCard';
import Spinner from '../components/Spinner';

const HomePage = () => {
    const [popularProducts, setPopularProducts] = useState([]);
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);

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
        const fetchData = async () => {
            setLoading(true);
            try {
                // Fetch all products to filter for 'popular' tag locally
                const productsRes = await getProducts({ limit: 500 });
                const categoriesRes = await getCategories();

                // --- THE FIX ---
                // Correctly access the nested .products array from the API response
                if (productsRes.data && Array.isArray(productsRes.data.products)) {
                    const allProducts = productsRes.data.products;
                    const popular = allProducts
                        .filter(p => Array.isArray(p.tags) && p.tags.includes('popular') && Array.isArray(p.variations) && p.variations.length > 0)
                        .slice(0, 8);
                    setPopularProducts(popular);
                }

                // Correctly access the nested .categories array
                if (categoriesRes.data && Array.isArray(categoriesRes.data.categories)) {
                    setCategories(categoriesRes.data.categories);
                }
            } catch (error) {
                console.error("Failed to fetch homepage data:", error);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    if (loading) {
        return <div className="flex justify-center items-center h-96"><Spinner /></div>;
    }

    return (
        <div className="px-6 py-6 space-y-12">
            {/* Categories Section */}
            <div>
                <div className="flex justify-between items-center mb-6">
                    <h2 className="font-bold text-sm tracking-wider uppercase">Categories</h2>
                    <Link to="/categories" className="text-gray-500 hover:text-accent">
                        <ChevronRight size={24} />
                    </Link>
                </div>
                {categories.length > 0 ? (
                    <div className="flex space-x-6 overflow-x-auto scrollbar-hide pb-4">
                        {categories.map((category) => {
                            const imageKey = category.toLowerCase();
                            const categoryLink = encodeURIComponent(imageKey);
                            return (
                                <Link key={category} to={`/category/${categoryLink}`} className="flex-shrink-0 flex flex-col items-center w-20 group">
                                    <div className="w-16 h-16 rounded-full overflow-hidden mb-3 border-2 border-gray-200 group-hover:border-accent transition-colors">
                                        <img src={categoryImages[imageKey] || categoryImages.default} alt={category} className="w-full h-full object-cover" />
                                    </div>
                                    <span className="text-sm font-medium text-center">{category}</span>
                                </Link>
                            );
                        })}
                    </div>
                ) : ( <p className="text-gray-500">No categories found.</p> )}
            </div>

            {/* Popular Section */}
            <div>
                <div className="flex justify-between items-center mb-6">
                    <h2 className="font-bold text-sm tracking-wider uppercase">Popular</h2>
                    <Link to="/products" className="text-gray-500 hover:text-accent">
                        <ChevronRight size={24} />
                    </Link>
                </div>
                {popularProducts.length > 0 ? (
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8">
                        {popularProducts.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                ) : ( <p className="text-gray-500">No popular products to display.</p> )}
            </div>
        </div>
    );
};

export default HomePage;