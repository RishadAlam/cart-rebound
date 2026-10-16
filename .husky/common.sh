#!/usr/bin/env sh
# Append, never prepend, so a writable user dir cannot shadow a system binary.
for dir in "/usr/local/bin" "/opt/homebrew/bin" "$HOME/.local/bin"; do
	case ":$PATH:" in
		*":$dir:"*) ;;
		*) [ -d "$dir" ] && PATH="$PATH:$dir" ;;
	esac
done

if ! command -v node >/dev/null 2>&1 && [ -d "$HOME/.nvm/versions/node" ]; then
	for bin in "$HOME/.nvm/versions/node"/*/bin; do
		[ -x "$bin/node" ] && PATH="$PATH:$bin"
	done
fi

export PATH
