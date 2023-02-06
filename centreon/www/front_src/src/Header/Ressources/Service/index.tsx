import { withTranslation } from "react-i18next";
import ServiceIcon from "@mui/icons-material/Grain";
import { MenuSkeleton } from "@centreon/ui";
import ItemLayout from "../../sharedUI/ItemLayout";
import ResourceCounters from "../../sharedUI/ResourceCounters";
import ResourceSubMenu from "../../sharedUI/ResourceSubMenu";

import getCounterPropsAdapter from "./getCounterPropsAdapter";
import useResourcesCounters from "../useResourcesDatas";
import { serviceStatusDecoder } from "../../api/decoders";
import { serviceStatusEndpoint } from "../../api/endpoints";

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = useResourcesCounters({
    endPoint: serviceStatusEndpoint,
    adapter: getCounterPropsAdapter,
    queryName: "services-counters",
    schema: serviceStatusDecoder,
  });

  if (!isAllowed) {
    return null;
  }

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  return (
    data && (
      <ItemLayout
        Icon={ServiceIcon}
        title="Services"
        testId="Services"
        showPendingBadge={data.hasPending}
        renderIndicators={() => <ResourceCounters counters={data.counters} />}
        renderSubMenu={() => <ResourceSubMenu items={data.items} />}
      />
    )
  );
};

export default withTranslation()(ServiceStatusCounter);
