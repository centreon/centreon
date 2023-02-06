import { withTranslation } from "react-i18next";
import PollerIcon from "@mui/icons-material/DeviceHub";
import { MenuSkeleton } from "@centreon/ui";
import ItemLayout from "../sharedUI/ItemLayout";
import PollerStatusIcon from "./PollerStatusIcon";
import { PollerSubMenu } from "./PollerSubMenu/PollerSubMenu";

import { usePollerDatas } from "./usePollerDatas";

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = usePollerDatas();

  if (!isAllowed) {
    return null;
  }

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  return (
    data && (
      <ItemLayout
        Icon={PollerIcon}
        title="Pollers"
        testId="Pollers"
        triggerToggle={(callback) => callback()}
        renderIndicators={() => (
          <PollerStatusIcon iconSeverities={data.iconSeverities} />
        )}
        renderSubMenu={({ closeSubMenu }) => (
          <PollerSubMenu {...data.subMenu} closeSubMenu={closeSubMenu} />
        )}
      />
    )
  );
};

export default withTranslation()(ServiceStatusCounter);
