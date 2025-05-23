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
    };
    // Add other blueprint fields as needed
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

export type PortfolioStoreType = Store<'portfolio', PortfolioState, {}, PortfolioActions>;

interface PortfolioActions {
    fetchPortfolios(this: PortfolioStoreType): Promise<void>;
    fetchPortfolioDetails(this: PortfolioStoreType, portfolioId: number): Promise<void>;
    createPortfolio(this: PortfolioStoreType, name: string, description?: string): Promise<void>;
    addUserCardToPortfolio(this: PortfolioStoreType, portfolioId: number, userCardId: number, quantity: number): Promise<void>;
    createUserCard(this: PortfolioStoreType, data: Omit<UserCard, 'id' | 'user_id' | 'pivot' | 'blueprint' | 'estimated_value_cents' | 'sold_price_cents' | 'sold_currency' | 'sold_at' | 'acquired_at' | 'notes' | 'purchase_currency'> & { blueprint_id: number; condition: string; language: string; purchase_price?: number | null; notes?: string; acquired_at?: string;}): Promise<UserCard>;
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
        async fetchPortfolios(this: PortfolioStoreType) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await axios.get<any>('http://localhost:8000/api/portfolios');
                console.log('API /api/portfolios response:', response);

                let portfoliosData: Portfolio[] = [];

                if (response.data && Array.isArray(response.data.data)) {
                    // Standard Laravel API Resource collection (e.g., from pagination)
                    portfoliosData = response.data.data;
                } else if (Array.isArray(response.data)) {
                    // Data might be a direct array (e.g. if withoutWrapping() is used, or not a resource collection)
                    portfoliosData = response.data;
                } else {
                    console.error('Unexpected response structure for /api/portfolios:', response.data);
                    this.error = 'Unexpected response structure from server.';
                    this.portfolios = []; // Clear portfolios on error
                    this.isLoading = false;
                    return;
                }
                
                this.portfolios = portfoliosData.map((p: Portfolio) => ({
                    ...p,
                    user_cards: p.user_cards || [], 
                }));

                // Fetch details only if portfolios were successfully loaded
                if (this.portfolios.length > 0) {
                    for (const portfolio of this.portfolios) {
                        await this.fetchPortfolioDetails(portfolio.id);
                    }
                }

            } catch (err) {
                this.error = 'Failed to fetch portfolios';
                console.error(err);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPortfolioDetails(this: PortfolioStoreType, portfolioId: number) {
            this.isLoading = true; 
            try {
                const response = await axios.get<any>(`/api/portfolios/${portfolioId}`);
                console.log(`API /api/portfolios/${portfolioId} response:`, response);
                
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

        async createPortfolio(this: PortfolioStoreType, name: string, description?: string) {
            this.isLoading = true;
            try {
                const response = await axios.post<Portfolio>('/api/portfolios', { name, description });
                this.portfolios.push(response.data);
            } catch (err) {
                this.error = 'Failed to create portfolio';
                console.error(err);
                throw err; // Rilancia per gestione nel componente
            } finally {
                this.isLoading = false;
            }
        },

        async addUserCardToPortfolio(this: PortfolioStoreType, portfolioId: number, userCardId: number, quantity: number) {
            try {
                const response = await axios.post<Portfolio>(`/api/portfolios/${portfolioId}/user-cards`, {
                    user_card_id: userCardId,
                    quantity,
                });
                // Aggiorna il portfolio nello store
                const index = this.portfolios.findIndex((p: Portfolio) => p.id === portfolioId);
                if (index !== -1) {
                    this.portfolios[index] = response.data;
                }
            } catch (error) {
                console.error('Error adding card to portfolio:', error);
                throw error;
            }
        },
        
        async createUserCard(this: PortfolioStoreType, data: Omit<UserCard, 'id' | 'user_id' | 'pivot' | 'blueprint' | 'estimated_value_cents' | 'sold_price_cents' | 'sold_currency' | 'sold_at' | 'acquired_at' | 'notes' | 'purchase_currency'> & { blueprint_id: number; condition: string; language: string; purchase_price?: number | null; notes?: string; acquired_at?: string;} ): Promise<UserCard> {
            const payload: any = { 
                blueprint_id: data.blueprint_id,
                condition: data.condition,
                language: data.language,
                notes: data.notes,
                acquired_at: data.acquired_at,
                // purchase_currency will be set if purchase_price is set, or default on backend
            };

            if (typeof data.purchase_price !== 'undefined') { // Check if purchase_price is explicitly passed
                if (data.purchase_price === null) {
                    payload.purchase_price_cents = null;
                } else {
                    payload.purchase_price_cents = Math.round(data.purchase_price * 100);
                    payload.purchase_currency = payload.purchase_currency || 'EUR'; // Default currency if price is set
                }
            }
            // No `delete payload.purchase_price;` needed as it's not part of the initial payload object

            try {
                const response = await axios.post<UserCard>('/api/user-cards', payload);
                // Non aggiungiamo UserCard direttamente a un portfolio qui, gestito da addUserCardToPortfolio
                // Ma potremmo volerla aggiungere a una lista generale di user cards dell'utente se la manteniamo nello store
                return response.data; // Ritorna la UserCard creata
            } catch (error) {
                console.error('Error creating user card:', error);
                throw error;
            }
        }
        // Altre azioni: updatePortfolio, deletePortfolio, updateUserCardInPortfolio, removeUserCardFromPortfolio, ecc.
    },
}); 