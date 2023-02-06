import { useState, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useAtomValue } from "jotai/utils";
import { useTranslation } from "react-i18next";

import { useFetchQuery } from "@centreon/ui";
import { refreshIntervalAtom, userAtom } from "@centreon/ui-context";

import { getPollerPropsAdapter } from "./getPollerPropsAdapter"
import useNavigation from "../../Navigation/useNavigation";
import { pollerListIssuesEndPoint } from '../api/endpoints'

export const usePollerDatas = () => {
    const [datas, setDatas] = useState(null);
    const [isAllowed, setIsAllowed] = useState<boolean>(true);
    const refetchInterval = useAtomValue(refreshIntervalAtom);
    const navigate = useNavigate();
    const { t } = useTranslation();
    const { allowedPages } = useNavigation();
    const { isExportButtonEnabled } = useAtomValue(userAtom);


    const { isLoading, error, data } = useFetchQuery({
        getQueryKey: () => [pollerListIssuesEndPoint, 'get-poller-status'],
        getEndpoint: () => pollerListIssuesEndPoint,
        // decoder: schema,
        queryOptions: {
            refetchInterval: refetchInterval * 1000, // refetchInterval from user or API response ?
        },
        catchError: ({ statusCode }) => {
            if (statusCode === 401) {
                setIsAllowed(false);
            }
        },
    });

    useEffect(() => {
        if (data) {
            setDatas(getPollerPropsAdapter({
                data,
                t,
                allowedPages,
                navigate,
                isExportButtonEnabled
            }))
        }
    }, [data]);


    return useMemo(
        () => ({ isLoading, error, data: datas, isAllowed }),
        [isLoading, error, datas]
    );
};

export default usePollerDatas;
