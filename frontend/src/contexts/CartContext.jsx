import React, { createContext, useState, useEffect, useContext } from 'react';
import { getProducts } from '../services/api';

const CartContext = createContext();
export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
    const [cartItems, setCartItems] = useState([]);
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);

    // Effect 1: Hydrate and Validate the cart from localStorage on initial app load.
    useEffect(() => {
        const hydrateAndValidateCart = async () => {
            setIsLoading(true);
            let staleCart = [];
            try {
                const localData = localStorage.getItem('glowCart');
                staleCart = localData ? JSON.parse(localData) : [];
            } catch (error) {
                console.error("Could not parse cart from localStorage", error);
                staleCart = [];
            }
            
            if (staleCart.length === 0) {
                setCartItems([]);
                setIsLoading(false);
                return;
            }

            try {
                const res = await getProducts({ limit: 1000 });
                const liveProducts = res.data?.products || [];
                const liveProductsMap = new Map(liveProducts.map(p => [p.id, p]));

                const validatedCart = staleCart.filter(item => {
                    const product = item?.product;
                    const variation = item?.variation;
                    if (!product || !variation) return false;

                    const liveProduct = liveProductsMap.get(product.id);
                    if (!liveProduct) return false;

                    const liveVariation = liveProduct.variations.find(v => v.variationId === variation.variationId);
                    if (!liveVariation || (liveVariation.stock || 0) === 0) return false;
                    
                    return true;
                }).map(validItem => {
                    const liveProduct = liveProductsMap.get(validItem.product.id);
                    const liveVariation = liveProduct.variations.find(v => v.variationId === validItem.variation.variationId);
                    const newQuantity = Math.min(validItem.quantity, liveVariation.stock);
                    return { ...validItem, product: liveProduct, variation: liveVariation, quantity: newQuantity };
                });

                setCartItems(validatedCart);
            } catch (error) {
                console.error("Failed to validate cart:", error);
                setCartItems([]);
            } finally {
                setIsLoading(false);
            }
        };
        hydrateAndValidateCart();
    }, []);

    // Effect 2: Save the cart to localStorage whenever it changes.
    useEffect(() => {
        if (!isLoading) {
            localStorage.setItem('glowCart', JSON.stringify(cartItems));
        }
    }, [cartItems, isLoading]);

    // --- Core Cart Functions ---
    const openCart = () => setIsCartOpen(true);
    const closeCart = () => setIsCartOpen(false);
    const toggleCart = () => setIsCartOpen(prev => !prev);

    const addToCart = (product, selectedVariation, quantity) => {
        setCartItems(prevItems => {
            const existingItem = prevItems.find(item => item.variation.variationId === selectedVariation.variationId);
            if (existingItem) {
                const newQuantity = existingItem.quantity + quantity;
                const maxStock = existingItem.variation.stock || 0;
                return prevItems.map(item =>
                    item.variation.variationId === selectedVariation.variationId
                        ? { ...item, quantity: Math.min(newQuantity, maxStock) }
                        : item
                );
            }
            return [...prevItems, { product, variation: selectedVariation, quantity }];
        });
        openCart();
    };

    const updateQuantity = (variationId, newQuantity) => {
        setCartItems(prevItems =>
            prevItems.map(item => {
                if (item.variation.variationId === variationId) {
                    const maxStock = item.variation.stock || 0;
                    const updatedQuantity = Math.min(newQuantity, maxStock);
                    return updatedQuantity > 0 ? { ...item, quantity: updatedQuantity } : null;
                }
                return item;
            }).filter(Boolean) // Remove null items (those whose quantity dropped to 0)
        );
    };

    const removeFromCart = (variationId) => {
        setCartItems(prevItems => prevItems.filter(item => item.variation.variationId !== variationId));
    };

    const clearCart = () => {
        setCartItems([]);
    };

    // --- Calculated Values ---
    const cartCount = cartItems.reduce((count, item) => count + item.quantity, 0);
    const subtotal = cartItems.reduce((sum, item) => sum + item.product.basePrice * item.quantity, 0);
    const shipping = subtotal > 0 ? 50.00 : 0; // INR
    const tax = subtotal * 0.05; // 5% example tax
    const total = subtotal + shipping + tax;

    // The complete value object provided to all consuming components.
    const value = {
        cartItems, isCartOpen, isLoading, cartCount, subtotal, shipping, tax, total,
        addToCart, updateQuantity, removeFromCart, clearCart,
        openCart, closeCart, toggleCart
    };

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};