import React, { createContext, useState, useEffect, useContext } from 'react';

const CartContext = createContext();
export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
    const [cartItems, setCartItems] = useState(() => {
        try {
            const localData = localStorage.getItem('cartItems');
            return localData ? JSON.parse(localData) : [];
        } catch (error) { return []; }
    });

    useEffect(() => {
        localStorage.setItem('cartItems', JSON.stringify(cartItems));
    }, [cartItems]);

    // CRITICAL CHANGE: Cart now stores the specific variation
    const addToCart = (product, selectedVariation, quantity) => {
        setCartItems(prevItems => {
            const existingItem = prevItems.find(item => item.variation.variationId === selectedVariation.variationId);
            if (existingItem) {
                return prevItems.map(item =>
                    item.variation.variationId === selectedVariation.variationId
                        ? { ...item, quantity: item.quantity + quantity }
                        : item
                );
            } else {
                return [...prevItems, { product, variation: selectedVariation, quantity }];
            }
        });
    };

    const updateQuantity = (variationId, newQuantity) => {
        if (newQuantity <= 0) {
            removeFromCart(variationId);
        } else {
            setCartItems(prevItems =>
                prevItems.map(item =>
                    item.variation.variationId === variationId
                        ? { ...item, quantity: newQuantity }
                        : item
                )
            );
        }
    };

    const removeFromCart = (variationId) => {
        setCartItems(prevItems => prevItems.filter(item => item.variation.variationId !== variationId));
    };

    const clearCart = () => setCartItems([]);

    const cartCount = cartItems.reduce((count, item) => count + item.quantity, 0);
    const subtotal = cartItems.reduce((sum, item) => sum + item.product.basePrice * item.quantity, 0);

    const value = { cartItems, addToCart, updateQuantity, removeFromCart, clearCart, cartCount, subtotal };
    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};