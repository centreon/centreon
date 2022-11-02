jest.mock('@centreon/ui-context', () => ({
  ...jest.requireActual('@centreon/ui-context'),
  ThemeMode: {
    light: 'light',
  },
}));
