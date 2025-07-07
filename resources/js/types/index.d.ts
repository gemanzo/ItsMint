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
  expansion?: {
    id: number;
    name: string;
    code: string;
  };
  collector_number?: string;
  latest_price?: {
    price_cents: number;
  };
}

export interface DetectedCard {
  id: number;
  name: string;
  confidence: number;
  blueprint_id?: number;
}

export interface CardScan {
  id: number;
  status: string;
  blueprint?: Blueprint;
  detected_cards?: DetectedCard[];
  created_at: string;
  confidence_score?: number;
}

export type PageProps<
  T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
  auth: {
    user: User;
  };
  ziggy: Config & { location: string };
};
