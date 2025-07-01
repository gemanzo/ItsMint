import { defineStore, Store } from 'pinia';
import axios from 'axios';

export interface Blueprint {
    id: number;
    name: string;
    image_url?: string;
    expansion?: {
        id: number;
        name: string;
        code: string;
    };
    latest_price?: {
        price_cents: number;
        is_foil?: boolean;
        language?: string;
    };
}

export interface UserCard {
    id: number;
    user_id: number;
    blueprint_id: number;
    condition: string;
    language: string;
    purchase_price_cents?: number | null;
    purchase_currency?: string;
    acquired_at?: string; // Assuming ISO string date
    sold_price_cents?: number | null;
    sold_currency?: string;
    sold_at?: string; // Assuming ISO string date
    notes?: string;
    blueprint?: Blueprint; // Optional, but likely present
    pivot: {
        portfolio_id: number; // Make sure these are included if they come from API
        user_card_id: number; // Make sure these are included if they come from API
        quantity: number;
        added_to_portfolio_at: string;
    };
    estimated_value_cents?: number | null; // Added for estimated value
}

export interface Portfolio {
    id: number;
    user_id: number;
    name: string;
    description?: string;
    user_cards_count?: number;
    user_cards?: UserCard[]; // Should now use the updated UserCard interface
    // Add other portfolio fields as needed
}

interface PortfolioState {
    portfolios: Portfolio[];
    selectedPortfolio: Portfolio | null;
    isLoading: boolean;
    error: string | null;
}

interface PortfolioActions {
    fetchPortfolios(): Promise<void>;
    fetchPortfolioDetails(portfolioId: number): Promise<void>;
    createPortfolio(name: string, description?: string): Promise<Portfolio>;
    addUserCardToPortfolio(portfolioId: number, userCardId: number, quantity: number): Promise<void>;
    createUserCard(data: Omit<UserCard, 'id' | 'user_id' | 'pivot' | 'blueprint' | 'estimated_value_cents' | 'sold_price_cents' | 'sold_currency' | 'sold_at' | 'acquired_at' | 'notes' | 'purchase_currency'> & { 
        blueprint_id: number; 
        condition: string; 
        language: string; 
        quantity: number; 
        purchase_price?: number | null; 
        notes?: string; 
        acquired_at?: string;
    }): Promise<UserCard | UserCard[]>;
    deletePortfolio(id: number): Promise<void>;
}

export const usePortfolioStore = defineStore('portfolio', {
    state: (): PortfolioState => ({
        portfolios: [],
        selectedPortfolio: null,
        isLoading: false,
        error: null,
    }),
    getters: {
        totalValue: (state: PortfolioState): number => {
            let total = 0;
            state.portfolios.forEach((portfolio: Portfolio) => {
                if (portfolio.user_cards) {
                    portfolio.user_cards.forEach((card: UserCard) => {
                        if (card.blueprint?.latest_price?.price_cents && card.pivot?.quantity) {
                            total += (card.blueprint.latest_price.price_cents / 100) * card.pivot.quantity;
                        }
                    });
                }
            });
            return total;
        },
        // Altri getters utili (es. carte per un portfolio specifico, valore di un singolo portfolio)
    },
    actions: {
        async fetchPortfolios() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get<any>('portfolios');
                const data = response.data.data || response.data;
                
                // Ensure we always set an array, even if the response is unexpected
                if (Array.isArray(data)) {
                    this.portfolios = data;
                } else {
                    console.warn('API response is not an array, setting empty array:', data);
                    this.portfolios = [];
                }
            } catch (error) {
                this.error = 'Failed to fetch portfolios';
                console.error(error);
                // Ensure portfolios remains an array even on error
                this.portfolios = [];
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPortfolioDetails(portfolioId: number) {
            this.isLoading = true; 
            try {
                const response = await axios.get<any>(`portfolios/${portfolioId}`);
                console.log('getPortfolioById response:', response.data);
                
                let portfolioData: Portfolio | null = null;

                if (response.data && response.data.data && typeof response.data.data === 'object') {
                    // Standard Laravel API Resource for a single item
                    portfolioData = response.data.data;
                } else if (response.data && typeof response.data === 'object' && !response.data.data) {
                    // Might be a direct object if not using API resource wrapping
                    portfolioData = response.data;
                } else {
                    console.error(`Unexpected response structure for /api/portfolios/${portfolioId}:`, response.data);
                    // Optionally set an error for this specific portfolio fetch
                    this.isLoading = false; // Reset loading for this specific call
                    return; // Don't proceed with inconsistent data
                }

                if (!portfolioData) {
                    console.error(`No portfolio data found for ID ${portfolioId} in response:`, response.data);
                    this.isLoading = false;
                    return;
                }

                const portfolioIndex = this.portfolios.findIndex((p: Portfolio) => p.id === portfolioId);
                if (portfolioIndex !== -1) {
                    this.portfolios[portfolioIndex] = {
                        ...portfolioData,
                        user_cards: portfolioData.user_cards || [],
                    };
                } else {
                    this.portfolios.push({
                        ...portfolioData,
                        user_cards: portfolioData.user_cards || [],
                    });
                }
            } catch (err) {
                console.error(`Failed to fetch details for portfolio ${portfolioId}:`, err);
                // Gestire errore specifico per questo portfolio
            } finally {
                this.isLoading = false;
            }
        },

        async createPortfolio(name: string, description?: string) {
            this.isLoading = true;
            try {
                const response = await axios.post<Portfolio>('portfolios', { name, description });
                const newPortfolio = response.data;
                this.portfolios.push(newPortfolio);
                return newPortfolio;
            } catch (err) {
                this.error = 'Failed to create portfolio';
                console.error(err);
                throw err; // Rilancia per gestione nel componente
            } finally {
                this.isLoading = false;
            }
        },

        async addUserCardToPortfolio(portfolioId: number, userCardId: number, quantity: number) {
            try {
                const response = await axios.post<Portfolio>(`portfolios/${portfolioId}/user-cards`, {
                    user_card_id: userCardId,
                    quantity
                });
                
                // Ensure portfolios is initialized as an array
                if (!Array.isArray(this.portfolios)) {
                    console.warn('this.portfolios is not an array, initializing as empty array:', this.portfolios);
                    this.portfolios = [];
                }
                
                // Aggiorna il portfolio nello store
                const index = this.portfolios.findIndex((p: Portfolio) => p.id === portfolioId);
                if (index !== -1) {
                    this.portfolios[index] = response.data;
                } else {
                    // Portfolio not in store, add it
                    this.portfolios.push(response.data);
                }
            } catch (error) {
                console.error('Error adding card to portfolio:', error);
                throw error;
            }
        },
        
        async createUserCard(data: Omit<UserCard, 'id' | 'user_id' | 'pivot' | 'blueprint' | 'estimated_value_cents' | 'sold_price_cents' | 'sold_currency' | 'sold_at' | 'acquired_at' | 'notes' | 'purchase_currency'> & { 
            blueprint_id: number; 
            condition: string; 
            language: string; 
            quantity: number; 
            purchase_price?: number | null; 
            notes?: string; 
            acquired_at?: string;
        }): Promise<UserCard | UserCard[]> {
            const payload: any = { 
                blueprint_id: data.blueprint_id,
                condition: data.condition,
                language: data.language,
                notes: data.notes,
                acquired_at: data.acquired_at,
                purchase_price: data.purchase_price, // Always include, even if null
                quantity: data.quantity,
            };

            // Set currency when price is provided
            if (data.purchase_price !== null && data.purchase_price !== undefined) {
                payload.purchase_currency = 'EUR';
            }

            console.log('Payload being sent to API:', payload);

            try {
                const response = await axios.post<UserCard | UserCard[]>('user-cards', payload);
                return response.data;
            } catch (error) {
                console.error('Error creating user card:', error);
                throw error;
            }
        },

        async deletePortfolio(id: number) {
            try {
                await axios.delete(`portfolios/${id}`);
                // La lista verrà aggiornata dal reload di inertia nel componente
            } catch (error) {
                console.error('Error deleting portfolio:', error);
                throw error; // Rilancia l'errore per gestirlo nel componente
            }
        },
        // Altre azioni: updatePortfolio, deletePortfolio, updateUserCardInPortfolio, removeUserCardFromPortfolio, ecc.
    },
}); 