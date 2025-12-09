/**
 * API Configuration
 * Automatically detects environment and uses correct API URL
 */

const API_CONFIG = {
  // Detect environment and set base URL
  baseURL: (() => {
    // Production: Use Vercel URL
    const vercelUrl = 'https://online-biz-permit.vercel.app';
    const isProduction = window.location.hostname !== 'localhost' && 
                        window.location.hostname !== '127.0.0.1' &&
                        !window.location.hostname.includes('localhost');
    
    if (isProduction) {
      return `${vercelUrl}/api`;
    }
    
    // Development: Use localhost
    return 'http://localhost:3000/api';
  })(),
  
  // Helper function to build full URL
  url: (endpoint) => {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    return `${API_CONFIG.baseURL}${cleanEndpoint}`;
  },
  
  // Helper for authenticated requests
  fetch: async (endpoint, options = {}) => {
    const token = localStorage.getItem('authToken'); // Adjust based on your auth storage
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };
    
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    
    const response = await fetch(API_CONFIG.url(endpoint), {
      ...options,
      headers
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Request failed' }));
      throw new Error(error.message || `HTTP ${response.status}`);
    }
    
    return response.json();
  }
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
  module.exports = API_CONFIG;
}

// Make available globally
window.API_CONFIG = API_CONFIG;

