import { useLocation } from 'react-router';

/**
 * Records the current router location in a hidden input, so specs assert
 * navigations instead of stubbing useNavigate as the Cypress specs did.
 * Render it inside the story router.
 */
export const LocationRecorder = (): JSX.Element => {
  const { pathname, search } = useLocation();

  return (
    <form hidden>
      <input data-testid="location" readOnly value={`${pathname}${search}`} />
    </form>
  );
};
