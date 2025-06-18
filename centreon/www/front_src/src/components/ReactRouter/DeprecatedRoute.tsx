import { useEffect } from 'react';

import { isNil } from 'ramda';
import { generatePath, useNavigate, useParams } from 'react-router';

import { PageSkeleton } from '@centreon/ui';

import { DeprecatedRoute as DeprecatedRouteModel } from '../../reactRoutes/deprecatedRoutes';

const DeprecatedRoute = ({
  newRoute
}: Pick<DeprecatedRouteModel, 'newRoute'>): JSX.Element => {
  const parameters = useParams();

  const navigate = useNavigate();

  useEffect(() => {
    if (isNil(newRoute.parameters)) {
      navigate(newRoute.path);

      return;
    }
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
