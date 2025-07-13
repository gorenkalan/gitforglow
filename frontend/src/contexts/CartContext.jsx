import React, { createContext, useState, useEffect, useContext } from 'react';

const CartContext = createContext();
export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
    // --- STATE MANAGEMENT ---
    const [cartItems, setCartItems] = useState([]);
    const [isCartOpen, setIsCartOpen] = useState(false);

    // --- EFFECT 1: Load cart from localStorage on initial app load ---
    // This is simple and will not crash the app.
    useEffect(() => {
        let storedCart = [];
        try {
            const localData = localStorage.getItem('glowCart');
            storedCart = localData ? JSON.parse(localData) : [];
        } catch (error) {
            console.error("Could not parse cart from localStorage", error);
            storedCart = [];
        }
        setCartItems(storedCart);
    }, []); // Runs only once when the app first loads.

    // --- EFFECT 2: Save cart to localStorage whenever it changes ---
    useEffect(() => {
        localStorage.setItem('glowCart', JSON.stringify(cartItems));
    }, [cartItems]);

    // --- CORE CART FUNCTIONS ---
    const openCart = () => setIsCartOpen(true);
    const closeCart = () => setIsCartOpen(false);
    const toggleCart = () => setIsCartOpen(prev => !prev);

    const addToCart = (product, selectedVariation, quantity) => {
        setCartItems(prevItems => {
            const existingItem = prevItems.find(item => item.variation.variationId === selectedVariation.variationId);
            if (existingItem) {
                const newQuantity = existingItem.quantity + quantity;
                // Use the live stock data from the variation object to cap the quantity
                const maxStock = selectedVariation.stock || newQuantity; 
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
                    // If the updated quantity is 0 or less, this will return null.
                    return updatedQuantity > 0 ? { ...item, quantity: updatedQuantity } : null;
                }
                return item;
            // The .filter(Boolean) will remove any null items from the array.
            }).filter(Boolean) 
        );
    };

    const removeFromCart = (variationId) => {
        setCartItems(prevItems => prevItems.filter(item => item.variation.variationId !== variationId));
    };

    const clearCart = () => {
        setCartItems([]);
    };

    // --- CALCULATED VALUES ---
    const cartCount = cartItems.reduce((count, item) => count + item.quantity, 0);
    const subtotal = cartItems.reduce((sum, item) => sum + item.product.basePrice * item.quantity, 0);
    const shipping = subtotal > 0 ? 50.00 : 0;
    const tax = subtotal * 0.05;
    const total = subtotal + shipping + tax;

    const value = {
        cartItems, isCartOpen, cartCount, subtotal, shipping, tax, total,
        addToCart, updateQuantity, removeFromCart, clearCart,
        openCart, closeCart, toggleCart
    };

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};