import { ReactElement, useMemo } from 'react';

import { endsWith } from 'ramda';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useLocation } from 'react-router';

import { Link } from '@mui/material';

interface Props {
  navbar: Array<{
    label: string;
    link: string;
  }>;
}

const Navbar = ({ navbar }: Props): ReactElement => {
  const { t } = useTranslation();
  const location = useLocation();

  const className = useMemo(
    () =>
      'text-text-primary cursor-pointer font-normal data-[selected=true]:text-primary-main data-[selected=true]:cursor-default data-[selected=true]:font-bold',
    []
  );

  return (
    <nav className="flex gap-6">
      {navbar.map(({ label, link }) => {
        return (
          <Link
            key={label}
            className={className}
            component={RouterLink}
            data-selected={endsWith(link, location.pathname)}
            to={link}
            underline={endsWith(link, location.pathname) ? 'none' : 'hover'}
          >
            {t(label)}
          </Link>
        );
      })}
    </nav>
  );
};

export default Navbar;
