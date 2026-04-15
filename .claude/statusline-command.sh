#!/usr/bin/env bash
# Claude Code status line script

#  Model: Sonnet 4.6   Ctx: 41.0% 82kt/200kt   In: 34t   Out: 8.8kt   Cached: 81.7kt   Cost: $0.93    Session: 9.0% (-2h59m)   Weekly: 32.0% (-48h)
#  cwd: /home/my/project   ? master (5f +53 -43)

input=$(cat)

# -- ANSI colors ----------------------------------------------------------------

R=$'\033[0m'       # reset
BOLD=$'\033[1m'
DIM=$'\033[2m'
CYAN=$'\033[36m'
GREEN=$'\033[32m'
RED=$'\033[31m'
YELLOW=$'\033[33m'
BLUE=$'\033[34m'
BCYAN=$'\033[1;36m'   # bold cyan
BYELLOW=$'\033[1;33m' # bold yellow

# -- helpers --------------------------------------------------------------------

fmt_k() {
  local n="${1:-0}"
  [ "$n" = "null" ] && n=0
  echo "$n" | awk '{
    if ($1 >= 1000) { printf "%.1fkt", $1/1000 }
    else            { printf "%dt",    $1       }
  }'
}

nonzero() { [ -n "$1" ] && [ "$1" != "0" ] && [ "$1" != "null" ]; }

# visible length of a string (strips ANSI escape codes)
vlen() { printf '%s' "$1" | sed 's/\x1b\[[0-9;]*m//g' | wc -m | tr -d ' '; }

# -- extract fields -------------------------------------------------------------

model_name=$(echo "$input" | jq -r '.model.display_name // .model.id // "Claude"')
effort=$(echo "$input"     | jq -r '.thinking.effort // .model.thinking_effort // empty')
cwd=$(echo "$input"        | jq -r '.cwd // .workspace.current_dir // ""')
vim_mode=$(echo "$input"   | jq -r '.vim.mode // empty')

used_pct=$(echo "$input"      | jq -r '.context_window.used_percentage      // empty')
ctx_size=$(echo "$input"      | jq -r '.context_window.context_window_size   // empty')

current_null=$(echo "$input" | jq -r '.context_window.current_usage == null')
input_tokens=0; output_tokens=0; cache_creation=0; cache_read=0
if [ "$current_null" = "false" ]; then
  input_tokens=$(echo "$input"   | jq -r '.context_window.current_usage.input_tokens                 // 0')
  output_tokens=$(echo "$input"  | jq -r '.context_window.current_usage.output_tokens                // 0')
  cache_creation=$(echo "$input" | jq -r '.context_window.current_usage.cache_creation_input_tokens  // 0')
  cache_read=$(echo "$input"     | jq -r '.context_window.current_usage.cache_read_input_tokens      // 0')
fi

total_in=$(echo "$input"  | jq -r '.context_window.total_input_tokens  // 0')
total_out=$(echo "$input" | jq -r '.context_window.total_output_tokens // 0')

# try several known field paths for cost
cost=$(echo "$input" | jq -r '
  .cost.total_cost_usd //
  .cost_usd //
  .session_cost_usd //
  .usage.cost_usd //
  .session.cost_usd //
  null
  | select(. != null and . != 0)
  | . * 1
' 2>/dev/null)

five_pct=$(echo "$input"    | jq -r '.rate_limits.five_hour.used_percentage // empty')
five_reset=$(echo "$input"  | jq -r '.rate_limits.five_hour.resets_at // empty')
seven_pct=$(echo "$input"    | jq -r '.rate_limits.seven_day.used_percentage // empty')
seven_reset=$(echo "$input"  | jq -r '.rate_limits.seven_day.resets_at // empty')

# -- git info -------------------------------------------------------------------

git_info=""
if [ -n "$cwd" ] && [ "$cwd" != "null" ]; then
  branch=$(GIT_OPTIONAL_LOCKS=0 git -C "$cwd" symbolic-ref --short HEAD 2>/dev/null)
  if [ -n "$branch" ]; then
    diff_stat=$(GIT_OPTIONAL_LOCKS=0 git -C "$cwd" diff --shortstat HEAD 2>/dev/null)
    files=""; ins=""; del=""
    if [ -n "$diff_stat" ]; then
      files=$(echo "$diff_stat" | grep -oE '[0-9]+ file'      | grep -oE '[0-9]+')
      ins=$(echo "$diff_stat"   | grep -oE '[0-9]+ insertion' | grep -oE '[0-9]+')
      del=$(echo "$diff_stat"   | grep -oE '[0-9]+ deletion'  | grep -oE '[0-9]+')
    fi
    diff_part=""
    if [ -n "$files" ] || [ -n "$ins" ] || [ -n "$del" ]; then
      diff_part=" ${DIM}(${R}${files:-0}f ${GREEN}+${ins:-0}${R} ${RED}-${del:-0}${R}${DIM})${R}"
    fi
    git_info="   ${CYAN}?${R} ${BOLD}${branch}${R}${diff_part}"
  fi
fi

# -- token / context formatting -------------------------------------------------

in_display=$(fmt_k "$total_in")
out_display=$(fmt_k "$total_out")
cached_total=$(awk "BEGIN{print ${cache_read:-0}+${cache_creation:-0}}")
cached_display=$(fmt_k "$cached_total")

ctx_used_display=""
if [ -n "$used_pct" ]; then
  ctx_tokens=$(awk "BEGIN{print ${input_tokens:-0}+${output_tokens:-0}+${cache_creation:-0}+${cache_read:-0}}")
  ctx_used_display=$(awk "BEGIN{printf \"%.1f%%\",${used_pct}}")
  if [ -n "$ctx_size" ] && [ "$ctx_size" != "0" ]; then
    fmt_k0() { echo "${1:-0}" | awk '{if($1>=1000){printf "%dkt",$1/1000+0.5}else{printf "%dt",$1}}'; }
    ctx_used_display="${ctx_used_display} $(fmt_k0 "$ctx_tokens")/$(fmt_k0 "$ctx_size")"
  fi
fi

cost_display=""
if [ -n "$cost" ]; then
  cost_display=$(awk "BEGIN{printf \"\$%.2f\",${cost}}")
fi

# -- rate limit formatting ------------------------------------------------------

five_pct_val="";  seven_pct_val=""
[ -n "$five_pct"  ] && five_pct_val=$(awk  "BEGIN{printf \"%.1f%%\",${five_pct}}")
[ -n "$seven_pct" ] && seven_pct_val=$(awk "BEGIN{printf \"%.1f%%\",${seven_pct}}")

# -- terminal width -------------------------------------------------------------

cols=$(tput cols 2>/dev/null || echo 80)

# -- line 1: model + context stats ---------------------------------------------

lbl() { printf '%s' "${DIM}${1}${R}"; }   # dim label
val() { printf '%s' "${1}";           }   # normal value

line1="   $(lbl 'Model:') ${BCYAN}${model_name}${R}"

[ -n "$effort"   ] && line1="${line1}   $(lbl 'Effort:') $(val "$effort")"
[ -n "$vim_mode" ] && line1="${line1}   $(lbl "${vim_mode}")"

[ -n "$ctx_used_display" ] && line1="${line1}   $(lbl 'Ctx:') $(val "$ctx_used_display")"
nonzero "$total_in"        && line1="${line1}   $(lbl 'In:') $(val "$in_display")"
nonzero "$total_out"       && line1="${line1}   $(lbl 'Out:') $(val "$out_display")"
nonzero "$cached_total"    && line1="${line1}   $(lbl 'Cached:') $(val "$cached_display")"
[ -n "$cost_display"     ] && line1="${line1}   $(lbl 'Cost:') ${BYELLOW}${cost_display}${R}"

reset_display=""
if [ -n "$five_reset" ]; then
  now=$(date +%s)
  delta=$(( five_reset - now ))
  if [ "$delta" -gt 0 ]; then
    reset_mins=$(( delta / 60 ))
    if [ "$reset_mins" -ge 60 ]; then
      reset_h=$(( reset_mins / 60 ))
      reset_m=$(( reset_mins % 60 ))
      reset_display=" ${DIM}(-${reset_h}h${reset_m}m)${R}"
    else
      reset_display=" ${DIM}(-${reset_mins}m)${R}"
    fi
  fi
fi

seven_reset_display=""
if [ -n "$seven_reset" ]; then
  now=$(date +%s)
  delta=$(( seven_reset - now ))
  if [ "$delta" -gt 0 ]; then
    reset_mins=$(( delta / 60 ))
    if [ "$reset_mins" -ge 600 ]; then
      reset_h=$(( (reset_mins + 30) / 60 ))
      seven_reset_display=" ${DIM}(-${reset_h}h)${R}"
    elif [ "$reset_mins" -ge 60 ]; then
      reset_h=$(( reset_mins / 60 ))
      reset_m=$(( reset_mins % 60 ))
      seven_reset_display=" ${DIM}(-${reset_h}h${reset_m}m)${R}"
    else
      seven_reset_display=" ${DIM}(-${reset_mins}m)${R}"
    fi
  fi
fi

rate_parts=""
[ -n "$five_pct_val"  ] && rate_parts="$(lbl 'Session:') ${YELLOW}${five_pct_val}${R}${reset_display}"
[ -n "$seven_pct_val" ] && rate_parts="${rate_parts}   $(lbl 'Weekly:') ${YELLOW}${seven_pct_val}${R}${seven_reset_display}"

if [ -n "$rate_parts" ]; then
  left_vlen=$(vlen "$line1")
  rate_vlen=$(vlen "   ${rate_parts}")
  pad=$((cols - left_vlen - rate_vlen))
  [ $pad -lt 1 ] && pad=1
  printf '%s%*s   %s\n' "$line1" "$pad" "" "$rate_parts"
else
  printf '%s\n' "$line1"
fi

# -- line 2: cwd / git ---------------------------------------------------------

env_file="$(git -C "$cwd" rev-parse --show-toplevel 2>/dev/null)/.env"
stack_name=$(grep -E '^COMPOSE_PROJECT_NAME=' "$env_file" 2>/dev/null | cut -d= -f2 | tr -d '\r')
stack_part=""
if [ -n "$stack_name" ]; then
  stack_startable=$(grep -E '^STACK_STARTABLE=' "$env_file" 2>/dev/null | cut -d= -f2 | tr -d '\r')
  [ "$stack_startable" = "false" ] && not_startable=" ${RED}(not startable)${R}" || not_startable=""
  stack_part=" ${DIM}Stack:${R} ${RED}${stack_name}${R}${not_startable}"
fi
line2="  ${stack_part} ${DIM}cwd:${R} ${BLUE}${cwd}${R}${git_info}"

printf '%s\n' "$line2"
