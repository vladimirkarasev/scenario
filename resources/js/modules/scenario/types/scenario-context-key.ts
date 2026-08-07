export enum ScenarioContextKey {
    ActionRuns = '_action_runs',
    ActionStages = '_action_stages',
    Call = '_call',
    CallStack = '_call_stack',
    Condition = '_condition',
    Operator = '_operator',
    Player = '_player',
    Project = '_project',
    Run = '_run',
    VariableMap = '_variable_map',
}

export function isSystemVariable(name: string): boolean {
    return name.startsWith('_')
}

export function isReservedScenarioVariable(name: string): boolean {
    return Object.values(ScenarioContextKey).includes(name as ScenarioContextKey)
}
