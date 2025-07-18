import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { getProducts } from '../services/api';
import useDebounce from '../hooks/useDebounce';
import ProductCard from '../components/ProductCard';
import Spinner from '../components/Spinner';
import { Search, ChevronLeft, ChevronRight } from 'lucide-react';

const ProductsPage = () => {
    const { categoryName: rawCategoryName } = useParams();
    const categoryName = rawCategoryName ? decodeURIComponent(rawCategoryName) : undefined;
    
    const [products, setProducts] = useState([]);
    const [pagination, setPagination] = useState({ currentPage: 1, totalPages: 1 });
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('name');
    const [currentPage, setCurrentPage] = useState(1);
    const debouncedSearchTerm = useDebounce(searchTerm, 300);

    // This single, robust useEffect hook handles all data fetching and filtering.
    useEffect(() => {
        const fetchProductsData = async () => {
            setLoading(true);
            try {
                const params = {
                    page: currentPage,
                    limit: 12,
                    sort_by: sortBy,
                };
                if (categoryName) {
                    params.category = categoryName;
                }
                if (debouncedSearchTerm) {
                    params.search = debouncedSearchTerm;
                }

                const res = await getProducts(params);
                
                // --- THE DEFINITIVE FIX ---
                setProducts(res?.data?.products || []);
                setPagination(res?.data?.pagination || { currentPage: 1, totalPages: 1 });
                
            } catch (error) {
                console.error("Failed to fetch products:", error);
                setProducts([]);
            } finally {
                setLoading(false);
            }
        };

        fetchProductsData();
    }, [currentPage, sortBy, categoryName, debouncedSearchTerm]);

    // This separate effect correctly resets the page to 1 ONLY when filters change.
    useEffect(() => {
        // We check currentPage !== 1 to prevent an infinite loop on the initial render.
        if (currentPage !== 1) {
            setCurrentPage(1);
        }
    }, [sortBy, categoryName, debouncedSearchTerm]);

    return (
        <div className="px-6 py-6">
            <h1 className="text-2xl font-bold mb-6 capitalize">{categoryName || 'All Products'}</h1>
            
            <div className="mb-6 space-y-4 md:flex md:space-y-0 md:space-x-4 md:items-center">
                <div className="relative flex-grow">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                    <input type="text" placeholder="Search products..." value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-full" />
                </div>
                
                <div className="flex-shrink-0">
                    <select value={sortBy} onChange={(e) => setSortBy(e.target.value)} className="px-3 py-2 border border-gray-300 rounded-full text-sm bg-white w-full">
                        <option value="name">Sort by Name</option>
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                        <option value="rating">Rating</option>
                    </select>
                </div>
            </div>

            {loading ? (
                 <div className="flex justify-center items-center h-96"><Spinner /></div>
            ) : products.length > 0 ? (
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 mb-8">
                    {products.map(product => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            ) : (
                <div className="text-center py-12 text-gray-500">
                    <h2 className="text-xl font-semibold mb-2">No Products Found</h2>
                    <p>Try adjusting your search or filters.</p>
                </div>
            )}

            {pagination.totalPages > 1 && (
                <div className="flex justify-center items-center space-x-2">
                    <button onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))} disabled={currentPage === 1} className="p-2 rounded-full bg-gray-200 disabled:opacity-50"><ChevronLeft /></button>
                    <span className="px-4 py-2 font-semibold">Page {currentPage} of {pagination.totalPages}</span>
                    <button onClick={() => setCurrentPage(prev => Math.min(prev + 1, pagination.totalPages))} disabled={currentPage === pagination.totalPages} className="p-2 rounded-full bg-gray-200 disabled:opacity-50"><ChevronRight /></button>
                </div>
            )}
        </div>
    );
};

export default ProductsPage;