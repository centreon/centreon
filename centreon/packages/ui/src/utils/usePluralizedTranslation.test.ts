import { act, renderHook } from '@testing-library/react';
import { useAtom } from 'jotai';

import { ListingVariant, userAtom } from '@centreon/ui-context';

import { usePluralizedTranslation } from './usePluralizedTranslation';

const baseUser = {
  alias: 'admin',
  isExportButtonEnabled: false,
  name: 'admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false,
  user_interface_density: ListingVariant.compact
};

describe('usePluralizedTranslation', () => {
  describe('English', () => {
    it('returns the plural of a word when the corresponding count is set', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'en'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 2,
          label: 'House'
        })
      ).toEqual('Houses');
    });

    it('returns the singular of a word when the corresponding count is set', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'en'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 1,
          label: 'House'
        })
      ).toEqual('House');
    });

    it('returns the plural of a word when the corresponding count is 0 and the language is english', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'en'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 0,
          label: 'House'
        })
      ).toEqual('Houses');
    });
  });

  describe('French', () => {
    it('returns the plural of a word when the corresponding count is set', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'fr'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 2,
          label: 'Maison'
        })
      ).toEqual('Maisons');
    });

    it('returns the singular of a word when the corresponding count is set', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'fr'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 1,
          label: 'Maison'
        })
      ).toEqual('Maison');
    });

    it('returns the singular of a word when the corresponding count is 0 and the language is english', () => {
      const { result } = renderHook(() => {
        return {
          pluralizedTranslation: usePluralizedTranslation(),
          userAtom: useAtom(userAtom)
        };
      });

      act(() => {
        result.current.userAtom[1]({
          ...baseUser,
          locale: 'fr'
        });
      });

      expect(
        result.current.pluralizedTranslation.pluralizedT({
          count: 0,
          label: 'Maison'
        })
      ).toEqual('Maison');
    });
  });
});
