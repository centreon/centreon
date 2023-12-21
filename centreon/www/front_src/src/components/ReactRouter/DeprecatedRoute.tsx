import { useEffect } from 'react';

import { generatePath, useNavigate, useParams } from 'react-router';

import { PageSkeleton } from '@centreon/ui';

import { DeprecatedRoute } from '../../reactRoutes/deprecatedRoutes';

const DeprecatedRoute = ({
  newRoute
}: Pick<DeprecatedRoute, 'newRoute'>): JSX.Element => {
  const parameters = useParams();

  const navigate = useNavigate();

  useEffect(() => {
    const newRouteParameters = newRoute.parameters.reduce(
      (acc, { property, defaultValue }): object => {
        if (defaultValue) {
          return {
            ...acc,
            [property]: defaultValue
          };
        }

        return {
          ...acc,
          [property]: parameters[property]
        };
      },
      {}
    );

    navigate(generatePath(newRoute.path, newRouteParameters));
  }, []);

  return <PageSkeleton displayHeaderAndNavigation={false} />;
};

export default DeprecatedRoute;
