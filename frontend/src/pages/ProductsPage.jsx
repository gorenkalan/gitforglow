import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { getProducts } from '../services/api';
import useDebounce from '../hooks/useDebounce';
import ProductCard from '../components/ProductCard';
import Spinner from '../components/Spinner';
import { Search, ChevronLeft, ChevronRight } from 'lucide-react';

const ProductsPage = () => {
    // Get the raw category name from the URL, which might be encoded (e.g., "body%20lotions")
    const { categoryName: rawCategoryName } = useParams();

    // --- FIX: Decode the category name ---
    // This converts "body%20lotions" back into "body lotions" so it can be used in the API call.
    const categoryName = rawCategoryName ? decodeURIComponent(rawCategoryName) : undefined;
    
    // State management for products, pagination, filters, and loading status
    const [products, setProducts] = useState([]);
    const [pagination, setPagination] = useState({ currentPage: 1, totalPages: 1 });
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('name');
    const [currentPage, setCurrentPage] = useState(1);

    // Debounce the search term to avoid excessive API calls while the user is typing
    const debouncedSearchTerm = useDebounce(searchTerm, 300);

    // Memoized function to fetch products from the API
    const fetchProducts = useCallback(async (pageToFetch) => {
        setLoading(true);
        try {
            const params = {
                page: pageToFetch,
                limit: 12,
                sort_by: sortBy,
            };
            // Only add the category to the params if it exists
            if (categoryName) {
                params.category = categoryName;
            }
            // Only add the search term if it exists
            if (debouncedSearchTerm) {
                params.search = debouncedSearchTerm;
            }

            const res = await getProducts(params);
            
            // Defensive checks to prevent crashes from bad API responses
            setProducts(res.data?.products || []);
            setPagination(res.data?.pagination || { currentPage: 1, totalPages: 1 });
            
        } catch (error) {
            console.error("Failed to fetch products:", error);
            setProducts([]); // Reset to empty array on error
        } finally {
            setLoading(false);
        }
    }, [sortBy, categoryName, debouncedSearchTerm]); // Dependencies for the fetch function

    // Effect to fetch products when the page number changes
    useEffect(() => {
        fetchProducts(currentPage);
    }, [currentPage, fetchProducts]);

    // Effect to reset to page 1 whenever a filter changes
    useEffect(() => {
        if (currentPage !== 1) {
            setCurrentPage(1);
        } else {
            // If we are already on page 1, we still need to trigger a refetch
            fetchProducts(1);
        }
    }, [sortBy, categoryName, debouncedSearchTerm]);

    return (
        <div className="px-6 py-6">
            {/* Display the decoded category name, or "All Products" as a fallback */}
            <h1 className="text-2xl font-bold mb-6 capitalize">{categoryName || 'All Products'}</h1>
            
            {/* Search and Filters Section */}
            <div className="mb-6 space-y-4 md:flex md:space-y-0 md:space-x-4 md:items-center">
                <div className="relative flex-grow">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                    <input
                        type="text"
                        placeholder="Search products..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-full"
                    />
                </div>
                
                <div className="flex-shrink-0">
                    <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-full text-sm bg-white w-full"
                    >
                        <option value="name">Sort by Name</option>
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                        <option value="rating">Rating</option>
                    </select>
                </div>
            </div>

            {/* Products Grid or Loading/Empty State */}
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

            {/* Pagination Controls */}
            {pagination.totalPages > 1 && (
                <div className="flex justify-center items-center space-x-2">
                    <button
                        onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                        disabled={currentPage === 1}
                        className="p-2 rounded-full bg-gray-200 disabled:opacity-50"
                    >
                        <ChevronLeft />
                    </button>
                    <span className="px-4 py-2 font-semibold">
                        Page {currentPage} of {pagination.totalPages}
                    </span>
                    <button
                        onClick={() => setCurrentPage(prev => Math.min(prev + 1, pagination.totalPages))}
                        disabled={currentPage === pagination.totalPages}
                        className="p-2 rounded-full bg-gray-200 disabled:opacity-50"
                    >
                        <ChevronRight />
                    </button>
                </div>
            )}
        </div>
    );
};

export default ProductsPage;