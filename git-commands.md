# Git 常用命令参考

> 按使用频率从高到低排序，包含中文注释与常见错误处理。

---

## 目录

1. [查看状态与历史](#1-查看状态与历史-★★★★★)（最高频）
2. [暂存与提交](#2-暂存与提交-★★★★★)
3. [远程同步](#3-远程同步-★★★★★)
4. [分支管理](#4-分支管理-★★★★☆)
5. [撤销与回退](#5-撤销与回退-★★★★☆)
6. [合并与变基](#6-合并与变基-★★★☆☆)
7. [暂存工作现场 Stash](#7-暂存工作现场-stash-★★★☆☆)
8. [初始化与配置](#8-初始化与配置-★★☆☆☆)
9. [标签管理](#9-标签管理-★★☆☆☆)
10. [实用技巧](#10-实用技巧)
11. [常见错误处理](#11-常见错误处理)

---

## 1. 查看状态与历史 ★★★★★

| 命令 | 说明 |
|------|------|
| `git status` | 查看工作区与暂存区的状态 |
| `git status -s` | 简洁模式显示状态 |
| `git log` | 查看完整提交历史 |
| `git log --oneline` | 单行简洁显示历史 |
| `git log --oneline --graph --all` | 图形化显示所有分支历史 |
| `git diff` | 查看工作区与暂存区的差异（未暂存） |
| `git diff --staged` | 查看暂存区与上次提交的差异 |
| `git diff <branch1> <branch2>` | 对比两个分支的差异 |
| `git show <hash>` | 查看某次提交的详细内容 |

```bash
# 查看当前工作区状态（最常用）
git status

# 简洁模式：M=已修改 A=已暂存新文件 ??=未追踪
git status -s

# 查看最近 5 条提交记录
git log --oneline -5

# 图形化显示所有分支的提交树（排查分支关系很好用）
git log --oneline --graph --all

# 查看工作区中尚未 add 的修改内容
git diff

# 查看已 add 但尚未 commit 的内容
git diff --staged

# 对比 main 与 feature 分支的差异
git diff main..feature/user-auth

# 查看某个文件的改动历史（含内容变化）
git log --follow -p src/User.php

# 搜索提交记录中引入某关键字的版本
git log -S "function login"
```

---

## 2. 暂存与提交 ★★★★★

| 命令 | 说明 |
|------|------|
| `git add <file>` | 将指定文件加入暂存区 |
| `git add .` | 将所有变更加入暂存区 |
| `git add -p` | 交互式选择要暂存的代码块 |
| `git commit -m "<msg>"` | 提交暂存区内容并附说明 |
| `git commit -am "<msg>"` | 跳过暂存，直接提交已追踪文件 |
| `git commit --amend` | 修改最后一次提交（内容或说明） |

```bash
# 暂存指定文件
git add src/User.php

# 暂存当前目录所有变更（包括新文件）
git add .

# 交互式暂存：逐块选择要提交的内容（精细化提交神器）
git add -p

# 提交，建议使用约定式提交格式
# feat=新功能 fix=修复 docs=文档 refactor=重构 chore=杂项
git commit -m "feat: 新增用户登录功能"

# 已追踪文件直接提交（跳过 git add，但不包含新文件）
git commit -am "fix: 修正配置文件中的拼写错误"

# 修改上一次提交的说明（未 push 时使用）
git commit --amend -m "fix: 修正配置文件路径错误"

# 追加文件到上一次提交（未 push 时使用）
git add 漏掉的文件.php
git commit --amend --no-edit
```

---

## 3. 远程同步 ★★★★★

| 命令 | 说明 |
|------|------|
| `git clone <url>` | 克隆远程仓库到本地 |
| `git fetch` | 拉取远程更新但不合并 |
| `git pull` | 拉取远程更新并自动合并 |
| `git pull --rebase` | 拉取远程更新并用 rebase 方式合并 |
| `git push origin <branch>` | 推送本地分支到远程 |
| `git push -u origin <branch>` | 推送并设置上游追踪关系 |
| `git remote -v` | 查看远程仓库地址 |

```bash
# 克隆远程仓库
git clone https://github.com/user/repo.git

# 克隆指定分支（只下载该分支，速度更快）
git clone -b develop --single-branch https://github.com/user/repo.git

# 拉取远程所有分支信息（不合并，用于查看远程是否有新内容）
git fetch origin

# 拉取并合并当前分支对应的远程分支
git pull origin main

# 用 rebase 方式拉取（保持线性历史，推荐团队协作时使用）
git pull --rebase origin main

# 首次推送新分支并建立追踪关系
git push -u origin feature/user-auth

# 后续推送只需
git push

# 删除远程分支
git push origin --delete feature/user-auth

# 查看所有远程仓库地址
git remote -v

# 修改远程地址（如从 HTTP 改为 SSH）
git remote set-url origin git@github.com:user/repo.git
```

---

## 4. 分支管理 ★★★★☆

| 命令 | 说明 |
|------|------|
| `git branch` | 列出本地所有分支 |
| `git branch -a` | 列出本地和远程所有分支 |
| `git branch <name>` | 创建新分支 |
| `git branch -d <name>` | 删除已合并的分支 |
| `git branch -D <name>` | 强制删除分支（未合并也删） |
| `git checkout -b <name>` | 创建并切换到新分支 |
| `git switch <branch>` | 切换分支（新语法，推荐） |
| `git switch -c <name>` | 创建并切换（新语法，推荐） |

```bash
# 列出本地分支，当前分支前有 * 号
git branch

# 列出所有分支（含远程），远程分支以 remotes/ 开头
git branch -a

# 基于当前分支创建新分支并立即切换（最常用）
git checkout -b feature/user-auth

# 新语法，等同于上面的命令（Git 2.23+）
git switch -c feature/user-auth

# 切换到已有分支
git switch main

# 快速切回上一个分支（- 代表上一个）
git switch -

# 删除已合并到主干的分支（安全删除）
git branch -d feature/user-auth

# 强制删除（分支未合并时使用，会丢失该分支上的提交）
git branch -D feature/user-auth

# 查看每个分支的最新提交信息
git branch -v

# 查看已合并到当前分支的所有分支（用于清理旧分支）
git branch --merged
```

---

## 5. 撤销与回退 ★★★★☆

| 命令 | 说明 |
|------|------|
| `git restore <file>` | 撤销工作区修改（恢复到暂存区或最近提交） |
| `git restore --staged <file>` | 取消暂存（从暂存区移回工作区） |
| `git reset --soft HEAD~1` | 撤销最近一次提交，变更保留在暂存区 |
| `git reset --mixed HEAD~1` | 撤销最近一次提交，变更回到工作区（默认） |
| `git reset --hard HEAD~1` | 撤销最近一次提交并丢弃所有变更（危险） |
| `git revert <hash>` | 生成新提交来反向抵消某次提交（安全） |
| `git reflog` | 查看所有操作历史（救命命令） |

```bash
# 撤销某个文件在工作区的修改（还原到上次 commit 的状态）
git restore index.php

# 撤销所有工作区修改（慎用，未暂存的修改全部丢失）
git restore .

# 将已 add 的文件从暂存区移回工作区（内容保留）
git restore --staged index.php

# 撤销最近一次提交，改动内容保留在暂存区（可重新修改后再提交）
git reset --soft HEAD~1

# 撤销最近一次提交，改动内容回到工作区（需要重新 add + commit）
git reset --mixed HEAD~1

# 撤销最近一次提交并完全丢弃改动（不可恢复，慎用）
git reset --hard HEAD~1

# 撤销某次已推送的提交（通过新增一个反向提交，对团队安全）
git revert a1b2c3d

# 查看所有操作记录（包括被 reset 掉的提交，用于误操作后恢复）
git reflog
# 找到目标 hash 后执行恢复
git reset --hard HEAD@{3}
```

---

## 6. 合并与变基 ★★★☆☆

| 命令 | 说明 |
|------|------|
| `git merge <branch>` | 将指定分支合并到当前分支 |
| `git merge --no-ff <branch>` | 禁止快进合并，保留合并提交节点 |
| `git merge --abort` | 中止正在进行的合并 |
| `git rebase <branch>` | 将当前分支变基到指定分支 |
| `git rebase --abort` | 中止正在进行的变基 |
| `git rebase --continue` | 解决冲突后继续变基 |
| `git cherry-pick <hash>` | 将某次提交应用到当前分支 |

```bash
# 切换到 main 并合并 feature 分支
git switch main
git merge feature/user-auth

# 保留合并节点（推荐用于 feature 合并 main，历史更清晰）
git merge --no-ff feature/user-auth -m "merge: 合并用户认证功能"

# 合并遇到冲突时，解决冲突后执行
git add 冲突文件.php
git merge --continue

# 放弃合并，回到合并前状态
git merge --abort

# 将 feature 分支变基到 main（使历史更线性，适合个人分支）
git switch feature/user-auth
git rebase main

# 变基冲突解决流程
# 1. 解决冲突文件
# 2. git add 冲突文件
# 3. 继续变基
git rebase --continue

# 放弃变基，回到变基前状态
git rebase --abort

# 将某次提交（如 hotfix）应用到当前分支
git cherry-pick a1b2c3d

# 挑选多个连续提交（包含两端）
git cherry-pick a1b2c3d^..f6g7h8i
```

---

## 7. 暂存工作现场 Stash ★★★☆☆

| 命令 | 说明 |
|------|------|
| `git stash` | 暂存当前所有工作区变更 |
| `git stash push -m "<msg>"` | 暂存并附加说明 |
| `git stash list` | 查看所有暂存记录 |
| `git stash pop` | 恢复最近一次暂存并删除记录 |
| `git stash apply stash@{n}` | 恢复指定暂存但保留记录 |
| `git stash drop stash@{n}` | 删除指定暂存记录 |
| `git stash clear` | 清除所有暂存记录 |

```bash
# 场景：正在开发 feature，突然需要切换去修复 bug
# 先把当前工作现场保存起来
git stash push -m "WIP: 用户登录功能开发到一半"

# 切换到 main 修复 bug
git switch main
git switch -c hotfix/login-crash
# ... 修复并提交 ...

# 回到 feature 分支，恢复工作现场
git switch feature/user-auth
git stash pop

# 查看所有暂存记录
git stash list
# stash@{0}: On feature/user-auth: WIP: 用户登录功能开发到一半

# 恢复指定暂存（不删除记录，可多次应用）
git stash apply stash@{0}

# 删除指定暂存
git stash drop stash@{0}

# 包含未追踪的新文件一起暂存
git stash push -u -m "含新文件的暂存"
```

---

## 8. 初始化与配置 ★★☆☆☆

| 命令 | 说明 |
|------|------|
| `git init` | 初始化本地仓库 |
| `git config --global user.name` | 设置全局用户名 |
| `git config --global user.email` | 设置全局邮箱 |
| `git config --list` | 查看所有配置 |

```bash
# 在当前目录初始化仓库
git init

# 设置全局用户名和邮箱（首次使用必须配置）
git config --global user.name "张三"
git config --global user.email "zhangsan@example.com"

# 仅为当前项目设置（覆盖全局配置）
git config user.name "工作账号"
git config user.email "work@company.com"

# 设置默认编辑器为 vim
git config --global core.editor vim

# 设置默认主分支名为 main（Git 2.28+）
git config --global init.defaultBranch main

# 查看所有配置及其来源文件
git config --list --show-origin

# 配置 SSH 代理（解决 GitHub push 慢的问题）
git config --global core.sshCommand "ssh -i ~/.ssh/id_rsa_github"
```

---

## 9. 标签管理 ★★☆☆☆

| 命令 | 说明 |
|------|------|
| `git tag` | 列出所有标签 |
| `git tag <name>` | 创建轻量标签 |
| `git tag -a <name> -m "<msg>"` | 创建附注标签（推荐） |
| `git push origin <tag>` | 推送指定标签到远程 |
| `git push origin --tags` | 推送所有标签到远程 |
| `git tag -d <name>` | 删除本地标签 |

```bash
# 查看所有标签
git tag

# 创建附注标签（包含打标签的人、时间和说明，推荐用于正式发布）
git tag -a v1.0.0 -m "Release version 1.0.0 - 正式发布"

# 给历史提交打标签
git tag -a v0.9.0 a1b2c3d -m "历史版本补打标签"

# 推送标签到远程（push 默认不推送标签）
git push origin v1.0.0

# 推送所有本地标签到远程
git push origin --tags

# 删除本地标签
git tag -d v1.0.0

# 删除远程标签（两步操作）
git tag -d v1.0.0
git push origin --delete v1.0.0

# 查看某个标签的详细信息
git show v1.0.0
```

---

## 10. 实用技巧

```bash
# ── 别名配置（减少重复输入）──────────────────────────
git config --global alias.st status        # git st
git config --global alias.co checkout      # git co
git config --global alias.br branch        # git br
git config --global alias.lg "log --oneline --graph --all"  # git lg

# ── 文件操作 ────────────────────────────────────────
# 重命名文件（git 能追踪到重命名，比直接 mv 更好）
git mv old_name.php new_name.php

# 删除文件并暂存删除操作
git rm old_file.txt

# 只从版本控制中移除，本地文件保留（常用于补加 .gitignore 规则后）
git rm --cached config/database.php

# ── 查找与调试 ───────────────────────────────────────
# 二分查找引入 bug 的提交（自动化 bisect）
git bisect start
git bisect bad                  # 当前版本有 bug
git bisect good v1.0.0          # 这个版本没 bug
# git 会自动 checkout 中间版本，测试后执行：
git bisect good  # 或 git bisect bad
git bisect reset # 查找结束后重置

# 查看某行代码最后一次是谁改的
git blame -L 10,20 src/User.php

# 清理未追踪的文件（先演习后执行）
git clean -n     # 预览会删除哪些文件
git clean -fd    # 执行删除（-f 强制 -d 包含目录）
```

---

## 11. 常见错误处理

### 错误 1：push 被拒绝（远程有新提交）

```
! [rejected] main -> main (fetch first)
error: failed to push some refs
```

```bash
# 原因：远程分支比本地新，需先拉取再推送
git pull --rebase origin main   # 用 rebase 保持线性历史
git push origin main

# 若 pull 产生冲突，解决后继续
git add 冲突文件.php
git rebase --continue
git push origin main
```

---

### 错误 2：合并冲突（Merge Conflict）

```
CONFLICT (content): Merge conflict in src/User.php
Automatic merge failed; fix conflicts and then commit the result.
```

```bash
# 1. 查看哪些文件有冲突
git status

# 2. 打开冲突文件，手动解决冲突
# 冲突标记格式：
# <<<<<<< HEAD        ← 当前分支的内容
# 本地的代码
# =======
# 远程/另一分支的代码
# >>>>>>> feature/xxx ← 合并过来的内容

# 3. 解决后将文件标记为已解决
git add src/User.php

# 4. 完成合并提交
git merge --continue
# 或者放弃合并回到之前状态
git merge --abort
```

---

### 错误 3：误删文件或误 reset（找回丢失的提交）

```bash
# 场景：执行了 git reset --hard 后想找回丢失的提交

# 1. 查看操作历史（所有 HEAD 移动记录）
git reflog

# 输出示例：
# a1b2c3d HEAD@{0}: reset: moving to HEAD~1
# f6g7h8i HEAD@{1}: commit: feat: 用户登录功能  ← 找到丢失的提交

# 2. 恢复到目标状态
git reset --hard HEAD@{1}

# 或者只把丢失的提交恢复为新提交
git cherry-pick f6g7h8i
```

---

### 错误 4：commit 了不该提交的文件（如密钥、密码）

```bash
# 场景：不小心把 .env 文件提交了，尚未 push

# 方案一：修改上一次提交（未 push）
git rm --cached .env            # 从版本控制移除，本地保留
echo ".env" >> .gitignore       # 加入 .gitignore
git add .gitignore
git commit --amend --no-edit    # 修正上一次提交

# 方案二：已经 push，需要清除历史中的敏感文件
# 注意：执行后需要强制推送，并通知团队成员重新 clone
git filter-branch --force --index-filter \
  'git rm --cached --ignore-unmatch .env' \
  --prune-empty --tag-name-filter cat -- --all
git push origin --force --all

# 更推荐使用 git-filter-repo 工具（更安全高效）
# pip install git-filter-repo
git filter-repo --invert-paths --path .env
```

---

### 错误 5：在错误的分支上提交了代码

```bash
# 场景：本应在 feature 分支提交，却提交到了 main

# 方案：将提交转移到正确分支
# 1. 记下提交的 hash
git log --oneline -3
# a1b2c3d feat: 用户登录功能  ← 这个提交提交错了

# 2. 切换到正确分支并 cherry-pick 过去
git switch feature/user-auth
git cherry-pick a1b2c3d

# 3. 回到 main 撤销该提交
git switch main
git reset --hard HEAD~1

# 如果 main 已推送到远程，谨慎使用 --force
git push origin main --force-with-lease  # 比 --force 更安全
```

---

### 错误 6：`.gitignore` 不生效

```bash
# 原因：文件已被 git 追踪，.gitignore 只对未追踪文件生效

# 解决：取消追踪，让 .gitignore 重新生效
git rm -r --cached .             # 移除所有追踪（仅移除索引，本地文件不删）
git add .                        # 重新添加（.gitignore 规则此时生效）
git commit -m "chore: 修正 .gitignore 配置"
```

---

### 错误 7：`git pull` 后出现大量无意义的 merge commit

```
Merge branch 'main' of github.com:user/repo
```

```bash
# 原因：默认 pull 使用 merge 策略，每次拉取都会产生 merge commit

# 解决方案一：本次 pull 使用 rebase
git pull --rebase origin main

# 解决方案二：全局设置 pull 默认使用 rebase（推荐）
git config --global pull.rebase true
```
