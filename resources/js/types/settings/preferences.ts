import type { MyPreferencesQuery } from '@/graphql/generated/graphql';

export type UserPreferences = MyPreferencesQuery['myPreferences'];

export type UserPreferencesResponse = {
    data: UserPreferences;
};
