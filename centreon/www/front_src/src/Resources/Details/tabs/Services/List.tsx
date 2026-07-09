import { equals, last } from 'ramda';
import { RefObject } from 'react';

import { Resource, Status } from '../../../models';
import ServiceCard from '../Details/ServiceCard';

interface Props {
  infiniteScrollTriggerRef: RefObject<HTMLDivElement>;
  onSelectService: (service: Resource) => void;
  services: Array<Resource>;
}

const ServiceList = ({
  services,
  onSelectService,
  infiniteScrollTriggerRef
}: Props): JSX.Element => {
  return (
    <>
      {services.map((service) => {
        const isLastService = equals(last(services), service);
        const { id, name, status, information, duration } = service;

        return (
          <div key={id}>
            <ServiceCard
              information={information}
              name={name}
              onSelect={(): void =>
                onSelectService({
                  ...service,
                  parent: undefined
                })
              }
              status={status as Status}
              subInformation={duration}
            />
            {isLastService && <div ref={infiniteScrollTriggerRef} />}
          </div>
        );
      })}
    </>
  );
};

export default ServiceList;
