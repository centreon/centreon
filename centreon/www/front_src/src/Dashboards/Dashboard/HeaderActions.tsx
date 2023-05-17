import { Button } from "@centreon/ui";
import { useTranslation } from "react-i18next";
import { labelCancel } from "./translatedLabels";

const HeaderActions = ():JSX.Element => {
  const { t } = useTranslation();
  return (
    <Button
      variant="ghost"
    >
      {t(labelCancel)}
      </Button>
  )
};

export default HeaderActions;