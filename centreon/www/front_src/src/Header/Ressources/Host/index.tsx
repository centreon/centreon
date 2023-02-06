import { withTranslation } from "react-i18next";
import HostIcon from "@mui/icons-material/Dns";
import { MenuSkeleton } from "@centreon/ui";
import ItemLayout from "../../sharedUI/ItemLayout";
import ResourceCounters from "../../sharedUI/ResourceCounters";
import ResourceSubMenu from "../../sharedUI/ResourceSubMenu";

import getCounterPropsAdapter from "./getCounterPropsAdapter";
import useResourcesCounters from "../useResourcesDatas";
import { hostStatusEndpoint } from "../../api/endpoints";
import { hostStatusDecoder } from "../../api/decoders";

const HostStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed, error } = useResourcesCounters({
    endPoint: hostStatusEndpoint,
    adapter: getCounterPropsAdapter,
    queryName: "hosts-counters",
    decoder: hostStatusDecoder,
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
        Icon={HostIcon}
        title="Hosts"
        testId="Hosts"
        showPendingBadge={data.hasPending}
        renderIndicators={() => <ResourceCounters counters={data.counters} />}
        renderSubMenu={() => <ResourceSubMenu items={data.items} />}
      />
    )
  );
};

export default withTranslation()(HostStatusCounter);
