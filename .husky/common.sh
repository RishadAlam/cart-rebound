#!/usr/bin/env sh
# Shared hook bootstrap.
#
# GUI git clients (GitHub Desktop, IDE integrations) run hooks with a minimal
# PATH that often omits Node — especially when Node is installed via a version
# manager such as nvm. Make the toolchain discoverable so the hooks behave the
# same whether they run from a terminal or a GUI.

# Append (never prepend) so existing system entries keep precedence and a
# writable user dir cannot shadow a system binary during a commit/push.
for dir in "/usr/local/bin" "/opt/homebrew/bin" "$HOME/.local/bin"; do
	case ":$PATH:" in
		*":$dir:"*) ;;
		*) [ -d "$dir" ] && PATH="$PATH:$dir" ;;
	esac
done

# nvm-installed Node (version-agnostic): add any installed version's bin dir.
if ! command -v node >/dev/null 2>&1 && [ -d "$HOME/.nvm/versions/node" ]; then
	for bin in "$HOME/.nvm/versions/node"/*/bin; do
		[ -x "$bin/node" ] && PATH="$PATH:$bin"
	done
fi

export PATH
