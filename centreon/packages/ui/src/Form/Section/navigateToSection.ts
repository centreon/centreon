export const sectionAnchorAttribute = 'data-section-group-form-id';

export const getSectionAnchors = (container: HTMLElement): Array<HTMLElement> =>
  Array.from(
    container.querySelectorAll<HTMLElement>(`[${sectionAnchorAttribute}]`)
  );

interface NavigateToSectionProps {
  container: HTMLElement | null;
  sectionName: string;
}

// The lookup stays inside the form: two forms can share a group name.
export const navigateToSection = ({
  container,
  sectionName
}: NavigateToSectionProps): void => {
  if (!container) {
    return;
  }

  const section = getSectionAnchors(container).find(
    (anchor) => anchor.getAttribute(sectionAnchorAttribute) === sectionName
  );

  section?.scrollIntoView({ behavior: 'smooth' });
};
