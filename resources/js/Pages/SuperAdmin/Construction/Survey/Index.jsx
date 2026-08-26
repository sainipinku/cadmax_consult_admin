import SurveyWorkspace from "@/Pages/Construction/Survey/SurveyWorkspace";

export default function SurveyIndex(props) {
    return (
        <SurveyWorkspace
            {...props}
            routePrefix="super.construction"
            variant="super"
        />
    );
}