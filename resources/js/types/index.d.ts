import { Config } from 'ziggy-js';

export interface Role {
  id: number;
  name: string;
  guard_name: string;
  created_at?: string;
  updated_at?: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  roles?: Role[];
}

export interface Blueprint {
  id: number;
  name: string;
  image_url?: string;
  expansion?: {
    id: number;
    name: string;
    code: string;
  };
  collector_number?: string;
  latest_price?: {
    price_cents: number;
    is_foil?: boolean;
    language?: string;
  };
}

export interface DetectedCard {
  id: number;
  name: string;
  confidence: number;
  blueprint_id?: number;
  image_url?: string;
  expansion?: {
    id: number;
    name: string;
    code: string;
  };
  card_number?: string;
  matched_text?: string;
}

export interface CardScan {
  id: number;
  status: string;
  blueprint?: Blueprint;
  detected_cards?: DetectedCard[];
  created_at: string;
  confidence_score?: number;
  error_message?: string;
  name?: string;
  expansion?: {
    id: number;
    name: string;
    code: string;
  };
  message?: string;
  suggestions?: string[];
}

export interface Portfolio {
  id: number;
  name: string;
  description?: string;
  created_at: string;
  updated_at: string;
  user_id: number;
  user_cards?: UserCard[];
}

export interface UserCard {
  id: number;
  blueprint_id: number;
  condition: string;
  language: string;
  is_foil: boolean;
  purchase_price_cents?: number;
  estimated_value_cents?: number;
  acquired_at?: string;
  notes?: string;
  blueprint?: Blueprint;
  pivot?: {
    quantity: number;
    portfolio_id: number;
    user_card_id: number;
  };
}

export type PageProps<
  T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
  auth: {
    user: User;
  };
  ziggy: Config & { location: string };
};
