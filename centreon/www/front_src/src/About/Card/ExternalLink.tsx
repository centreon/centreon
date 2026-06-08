import { Link } from '@mui/material';

import { useTranslation } from 'react-i18next';

interface Props {
  ariaLabel: string;
  children: React.ReactNode;
  className?: string;
  href: string;
}

const accentColor = '#2e68aa';

const ExternalLink = ({
  href,
  ariaLabel,
  children,
  className
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Link
      aria-label={t(ariaLabel)}
      className={className}
      href={href}
      rel="noreferrer noopener"
      sx={{ color: accentColor, fontWeight: 500 }}
      target="_blank"
      underline="hover"
    >
      {children}
    </Link>
  );
};

export default ExternalLink;
