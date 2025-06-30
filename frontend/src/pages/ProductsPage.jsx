import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { getProducts } from '../services/api';
import useDebounce from '../hooks/useDebounce';
import ProductCard from '../components/ProductCard';
import Spinner from '../components/Spinner';
import { Search, ChevronLeft, ChevronRight } from 'lucide-react';

const ProductsPage = () => {
    const { categoryName } = useParams();
    const [products, setProducts] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(true);
    
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('name');
    const [currentPage, setCurrentPage] = useState(1);

    const debouncedSearchTerm = useDebounce(searchTerm, 300);

    const fetchProducts = useCallback(async () => {
        setLoading(true);
        try {
            const params = {
                page: currentPage,
                limit: 12,
                sort_by: sortBy,
            };
            if (categoryName) params.category = categoryName;
            if (debouncedSearchTerm) params.search = debouncedSearchTerm;

            const res = await getProducts(params);
            setProducts(res.data.products);
            setPagination(res.data.pagination);
        } catch (error) {
            console.error("Failed to fetch products:", error);
        } finally {
            setLoading(false);
        }
    }, [currentPage, sortBy, categoryName, debouncedSearchTerm]);

    useEffect(() => {
        fetchProducts();
    }, [fetchProducts]);

    useEffect(() => {
        // Reset to page 1 when filters change
        setCurrentPage(1);
    }, [sortBy, categoryName, debouncedSearchTerm]);


    return (
        <div className="px-6 py-6">
            <h1 className="text-2xl font-bold mb-6 capitalize">{categoryName || 'All Products'}</h1>
            {/* Search and Filters */}
            <div className="mb-6 space-y-4">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
                    <input
                        type="text"
                        placeholder="Search products..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-full"
                    />
                </div>
                
                <div className="flex flex-wrap gap-2">
                    <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-full text-sm bg-white"
                    >
                        <option value="name">Sort by Name</option>
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                        <option value="rating">Rating</option>
                    </select>
                </div>
            </div>

            {/* Products Grid */}
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
                    <p>No products found. Try adjusting your filters.</p>
                </div>
            )}

            {/* Pagination */}
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
                        {currentPage} of {pagination.totalPages}
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