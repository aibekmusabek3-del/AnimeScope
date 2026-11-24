export function targetTime(position,duration){return Math.min(Math.max(0,duration),Math.max(0,position));}
export function shouldSeek(current,target,changed=false){return changed||Math.abs(current-target)>1.6;}
